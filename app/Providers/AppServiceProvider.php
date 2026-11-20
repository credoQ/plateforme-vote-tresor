<?php

namespace App\Providers;

use App\Actions\Audit\Journaliser;
use App\Actions\Documents\EnteteDocument;
use App\Enums\Acces\CodeRole;
use App\Enums\Audit\ActionAuditee;
use App\Models\Edition;
use App\Models\User;
use App\View\Composers\EspaceElecteurComposer;
use App\View\Composers\FiltresComposer;
use App\View\Composers\NavigationComposer;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as Vue;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Droits par profil (R2). Les droits sur un objet précis (candidate, agent, inscription) sont dans app/Policies.
     */
    public function boot(): void
    {
        Gate::define('administrer', fn (User $user): bool => $user->aLeRole(CodeRole::Administrateur));
        Gate::define('piloter', fn (User $user): bool => $user->aLeRole(CodeRole::Comite));
        Gate::define('controler-listes', fn (User $user): bool => $user->aLeRole(CodeRole::Comite, CodeRole::PointFocal));
        Gate::define('observer', fn (User $user): bool => $user->aLeRole(CodeRole::Observateur, CodeRole::Comite, CodeRole::Administrateur));
        Gate::define('voter', fn (User $user): bool => $user->agent_id !== null && $user->aLeRole(CodeRole::Electeur));

        $this->journaliserLesConnexions();

        // Les vues ne font aucun traitement serveur : les données des gabarits leur sont fournies ici.
        View::composer('components.layouts.app', NavigationComposer::class);
        View::composer('components.layouts.electeur', EspaceElecteurComposer::class);
        View::composer('components.barre-filtres', FiltresComposer::class);
        View::composer('components.pdf.document', fn (Vue $vue) => $vue->with('images', app(EnteteDocument::class)->images()));
        View::composer('mail::message', fn (Vue $vue) => $vue->with('editionEnCours', Edition::anneeEnCours()));

        // En développement (« composer run dev »), le processus de file traite aussi les files des e-mails, par priorité,
        // avec une pause de 2 secondes entre deux tâches : la boîte de test Mailtrap refuse plusieurs e-mails par seconde.
        DevCommands::artisan('queue:listen --queue=urgent,mails,default --tries=1 --timeout=0 --rest=2', 'queue');
        DevCommands::artisan('schedule:work', 'planificateur');
    }

    /**
     * R56 : demandes de code, connexions réussies et échouées, déconnexions. Le code n'est jamais enregistré.
     */
    private function journaliserLesConnexions(): void
    {
        Event::listen(Login::class, function (Login $evenement): void {
            if (! $evenement->user instanceof User) {
                return;
            }

            DB::transaction(function () use ($evenement): void {
                $evenement->user->forceFill(['derniere_connexion' => now()])->saveQuietly();
                app(Journaliser::class)->handle(ActionAuditee::Connexion, $evenement->user, $evenement->remember ? ['souvenir' => true] : [], $evenement->user);
            });
        });

        Event::listen(Failed::class, function (Failed $evenement): void {
            $login = mb_substr(trim((string) ($evenement->credentials['login'] ?? '')), 0, 60);
            app(Journaliser::class)->handle(ActionAuditee::ConnexionEchouee, null, [], null, $login === '' ? null : $login);
        });

        Event::listen(Logout::class, function (Logout $evenement): void {
            if ($evenement->user instanceof User) {
                app(Journaliser::class)->handle(ActionAuditee::Deconnexion, $evenement->user, [], $evenement->user);
            }
        });
    }
}
