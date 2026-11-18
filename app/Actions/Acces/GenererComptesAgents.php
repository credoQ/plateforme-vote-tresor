<?php

namespace App\Actions\Acces;

use App\Actions\Audit\Journaliser;
use App\Enums\Acces\CodeRole;
use App\Enums\Audit\ActionAuditee;
use App\Models\Agent;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Crée un compte électeur pour chaque agent en service qui n'en a pas (R3, R13) : identifiant = matricule.
 * Pas de mot de passe : l'agent se connecte avec un code envoyé à l'adresse e-mail de sa fiche (R42).
 */
class GenererComptesAgents
{
    public function __construct(private Journaliser $journal) {}

    /**
     * @return array{crees: int, sans_email: list<array{matricule: string, nom: string, structure: string}>}
     */
    public function handle(?CarbonInterface $date = null): array
    {
        $roleElecteur = Role::where('code', CodeRole::Electeur->value)->firstOrFail();
        $crees = 0;

        Agent::enServiceLe($date)
            ->whereDoesntHave('compte')
            ->whereNotIn('matricule', User::select('login'))
            ->chunkById(200, function ($agents) use ($roleElecteur, &$crees): void {
                DB::transaction(function () use ($agents, $roleElecteur, &$crees): void {
                    foreach ($agents as $agent) {
                        User::create([
                            'name' => $agent->nomComplet(),
                            'login' => $agent->matricule,
                            'agent_id' => $agent->id,
                        ])->roles()->attach($roleElecteur);
                    }

                    // Journalisé dans la transaction du lot : un lot créé l'est toujours avec sa trace (R56).
                    $this->journal->handle(ActionAuditee::ComptesGeneres, null, ['nombre' => $agents->count()]);
                    $crees += $agents->count();
                });
            });

        // Comptes qui ne pourront pas recevoir de code tant qu'aucune adresse n'est enregistrée.
        $sansEmail = User::query()
            ->whereHas('roles', fn ($q) => $q->where('code', CodeRole::Electeur->value))
            ->whereNull('email')
            ->whereHas('agent', fn ($q) => $q->enServiceLe($date)->where(fn ($e) => $e->whereNull('email')->orWhere('email', '')))
            ->with('agent')
            ->orderBy('login')
            ->get()
            ->map(fn (User $compte): array => [
                'matricule' => $compte->login,
                'nom' => $compte->name,
                'structure' => $compte->agent->affectationLe($date)?->structure->code ?? '',
            ])
            ->all();

        return ['crees' => $crees, 'sans_email' => $sansEmail];
    }
}
