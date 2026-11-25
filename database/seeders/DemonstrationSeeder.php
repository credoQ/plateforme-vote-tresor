<?php

namespace Database\Seeders;

use App\Actions\Editions\EnregistrerEdition;
use App\Actions\Editions\LancerEdition;
use App\Actions\Editions\OuvrirPhase;
use App\Actions\Editions\ParametrerPhase;
use App\Actions\Scrutins\ArreterListes;
use App\Actions\Scrutins\GenererScrutins;
use App\Actions\Scrutins\OuvrirScrutin;
use App\Actions\Scrutins\PlanifierScrutin;
use App\Models\Edition;
use App\Models\Phase;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Données de démonstration pour une recette manuelle, à lancer à la main sur une base de développement :
 * php artisan db:seed --class=DemonstrationSeeder
 *
 * Charge les données de test (DonneesDeTestSeeder : agents, comptes, femme modèle inéligible), puis une édition
 * de l'année en cours avec l'organisation par défaut, lancée, dont la phase 1 est ouverte et les scrutins ouverts
 * pour sept jours. Les actions de la plateforme sont utilisées, comme le ferait le comité. Les e-mails partent par
 * la file d'attente.
 */
class DemonstrationSeeder extends Seeder
{
    public function run(): void
    {
        $annee = (int) now()->year;
        if (Edition::where('annee', $annee)->exists()) {
            throw new RuntimeException("La base contient déjà une édition {$annee} : la démonstration se charge sur une base sans données.");
        }

        $this->call(DonneesDeTestSeeder::class);
        $edition = DB::transaction(fn (): Edition => $this->ouvrirLEdition($annee));

        $this->command?->info("Édition {$edition->annee} lancée, phase 1 ouverte : {$edition->scrutins()->count()} scrutins ouverts jusqu’au ".now()->addDays(7)->format('d/m/Y').'.');
        $this->command?->line('Les codes de connexion s’écrivent dans storage/logs/laravel.log, une fois la file traitée : php artisan queue:work --queue=urgent,mails,default');
    }

    /**
     * Crée, planifie et lance l'édition, génère et ouvre les scrutins de la phase 1.
     */
    private function ouvrirLEdition(int $annee): Edition
    {
        $edition = app(EnregistrerEdition::class)->handle([
            'annee' => $annee,
            'libelle' => "Femme modèle du Trésor {$annee}",
            'date_debut' => now()->subDays(3)->toDateString(),
            'date_fin' => now()->addDays(45)->toDateString(),
        ]);

        $calendrier = [
            1 => [now()->subDays(3), now()->addDays(10)],
            2 => [now()->addDays(11), now()->addDays(25)],
            3 => [now()->addDays(26), now()->addDays(45)],
        ];
        foreach ($calendrier as $numero => [$debut, $fin]) {
            app(ParametrerPhase::class)->handle($edition->phase($numero), ['date_debut' => $debut->toDateString(), 'date_fin' => $fin->toDateString()]);
        }

        $primaires = $edition->fresh()->phase(Phase::PRIMAIRES);
        app(GenererScrutins::class)->handle($primaires);
        app(LancerEdition::class)->handle($edition->fresh());
        app(ArreterListes::class)->handle($primaires);
        // Les scrutins sont planifiés avant l'ouverture de la phase (R19), puis s'ouvrent aussitôt.
        foreach ($primaires->scrutins()->get() as $scrutin) {
            app(PlanifierScrutin::class)->handle($scrutin->fresh(), now()->subMinutes(5), now()->addDays(7)->setTime(18, 0));
        }
        app(OuvrirPhase::class)->handle($primaires->fresh());

        foreach ($primaires->scrutins()->get() as $scrutin) {
            app(OuvrirScrutin::class)->handle($scrutin->fresh());
        }

        return $edition->fresh();
    }
}
