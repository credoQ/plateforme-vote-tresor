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
use App\Enums\Acces\CodeRole;
use App\Enums\Editions\SourceFemmeModele;
use App\Models\Agent;
use App\Models\Edition;
use App\Models\FemmeModele;
use App\Models\Phase;
use App\Models\Role;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Données de démonstration pour une recette manuelle, à lancer à la main sur une base de développement :
 * php artisan db:seed --class=DemonstrationSeeder
 *
 * Crée des agents dans quelques structures, leurs comptes (électeurs, comité, point focal, administrateur,
 * observateur), une femme modèle 2024 inéligible, puis une édition de l'année en cours avec l'organisation par
 * défaut, lancée, dont la phase 1 est ouverte et les scrutins ouverts pour sept jours. Les actions de la plateforme
 * sont utilisées, comme le ferait le comité. Les e-mails partent par la file d'attente.
 */
class DemonstrationSeeder extends Seeder
{
    /**
     * Agents par structure : [nom, prénoms, sexe].
     *
     * @var array<string, list<array{0: string, 1: string, 2: string}>>
     */
    private const AGENTS = [
        'DIA' => [['AHOUANSOU', 'Clarisse', 'F'], ['KPADE', 'Inès', 'F'], ['DOSSOU', 'Prisca', 'F'], ['GBAGUIDI', 'Florence', 'F'], ['HOUNKPE', 'Romuald', 'M'], ['ADJOVI', 'Serge', 'M']],
        'DMG' => [['SOGLO', 'Bénédicte', 'F'], ['AGOSSOU', 'Carine', 'F'], ['TOSSOU', 'Marcel', 'M']],
        'DR' => [['KOUTON', 'Edwige', 'F'], ['ZINSOU', 'Paul', 'M']],
        'TD-ATL' => [['HOUNSOU', 'Afiavi', 'F'], ['AKPLOGAN', 'Mireille', 'F'], ['DEGBE', 'Jean', 'M']],
        'TC-ABOMEY-CALAVI' => [['ADJAHO', 'Sandrine', 'F'], ['KIKI', 'Armand', 'M']],
        'TC-OUIDAH' => [['AHOUANDJINOU', 'Sandrine', 'F'], ['LOKO', 'Clément', 'M']],
        'TD-LIT' => [['SAGBO', 'Rachelle', 'F'], ['HOUETO', 'Éric', 'M']],
        'TD-ZOU' => [['AKAKPO', 'Huguette', 'F']],
        'TD-BOR' => [['BIO', 'Ibrahim', 'M']],
    ];

    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        $annee = (int) now()->year;
        if (Edition::where('annee', $annee)->exists() || Agent::exists()) {
            throw new RuntimeException("La base contient déjà des agents ou une édition {$annee} : la démonstration se charge sur une base sans données.");
        }

        [$comptes, $edition] = DB::transaction(function () use ($annee): array {
            $agents = $this->creerAgents();
            $comptes = $this->creerComptes($agents);

            // Femme modèle il y a deux ans, importée : inéligible cette année avec le délai par défaut de 3 ans.
            $florence = $agents['GBAGUIDI Florence'];
            FemmeModele::create(['matricule' => $florence->matricule, 'agent_id' => $florence->id, 'nom_complet' => $florence->nomComplet(), 'annee' => $annee - 2, 'scrutin' => 'Finale', 'source' => SourceFemmeModele::Import]);

            return [$comptes, $this->ouvrirLEdition($annee)];
        });

        $this->command?->info("Édition {$edition->annee} lancée, phase 1 ouverte : {$edition->scrutins()->count()} scrutins ouverts jusqu’au ".now()->addDays(7)->format('d/m/Y').'.');
        $this->command?->table(['Profil', 'Identifiant (login)', 'Adresse e-mail'], $comptes);
        $this->command?->line('Les codes de connexion s’écrivent dans storage/logs/laravel.log, une fois la file traitée : php artisan queue:work --queue=urgent,mails,default');
    }

    /**
     * @return array<string, Agent>
     */
    private function creerAgents(): array
    {
        $agents = [];
        $matricule = 104800;

        foreach (self::AGENTS as $code => $liste) {
            $structure = Structure::where('code', $code)->firstOrFail();
            foreach ($liste as [$nom, $prenoms, $sexe]) {
                $agent = Agent::factory()->{$sexe === 'F' ? 'femme' : 'homme'}()->affecteA($structure, Carbon::parse('2018-01-02'))->create([
                    'matricule' => (string) ++$matricule,
                    'nom' => $nom,
                    'prenoms' => $prenoms,
                    'email' => Str::slug("{$prenoms}.{$nom}", '.').'@demo.test',
                ]);
                $agents["{$nom} {$prenoms}"] = $agent;
            }
        }

        return $agents;
    }

    /**
     * Un compte électeur par agent, puis les profils de gestion.
     *
     * @param  array<string, Agent>  $agents
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function creerComptes(array $agents): array
    {
        $electeur = Role::where('code', CodeRole::Electeur->value)->firstOrFail();
        foreach ($agents as $agent) {
            User::create(['name' => $agent->nomComplet(), 'login' => $agent->matricule, 'email' => $agent->email, 'agent_id' => $agent->id, 'actif' => true])
                ->roles()->attach($electeur);
        }

        // Le comité est un agent qui vote aussi ; le point focal est celui de la DIA.
        $comite = User::where('agent_id', $agents['HOUNKPE Romuald']->id)->firstOrFail();
        $comite->roles()->attach(Role::where('code', CodeRole::Comite->value)->firstOrFail());
        $pointFocal = User::where('agent_id', $agents['ADJOVI Serge']->id)->firstOrFail();
        $pointFocal->update(['structure_id' => Structure::where('code', 'DIA')->value('id')]);
        $pointFocal->roles()->attach(Role::where('code', CodeRole::PointFocal->value)->firstOrFail());

        User::factory()->avecRoles(CodeRole::Administrateur)->create(['name' => 'Administrateur technique', 'login' => 'ADMIN', 'email' => 'admin@demo.test']);
        User::factory()->avecRoles(CodeRole::Observateur)->create(['name' => 'Observatrice externe', 'login' => 'OBSERVATEUR', 'email' => 'observateur@demo.test']);

        return [
            ['Comité d’organisation (et électeur)', $comite->login, $comite->email],
            ['Point focal de la DIA (et électeur)', $pointFocal->login, $pointFocal->email],
            ['Administrateur', 'ADMIN', 'admin@demo.test'],
            ['Observateur', 'OBSERVATEUR', 'observateur@demo.test'],
            ['Électrice DIA (candidate)', $agents['AHOUANSOU Clarisse']->matricule, $agents['AHOUANSOU Clarisse']->email],
            ['Électrice DIA (femme modèle 2024, inéligible)', $agents['GBAGUIDI Florence']->matricule, $agents['GBAGUIDI Florence']->email],
            ['Électrice Atlantique (candidate)', $agents['HOUNSOU Afiavi']->matricule, $agents['HOUNSOU Afiavi']->email],
            ['Électeur Atlantique', $agents['DEGBE Jean']->matricule, $agents['DEGBE Jean']->email],
            ['Électeur Borgou (sans scrutin)', $agents['BIO Ibrahim']->matricule, $agents['BIO Ibrahim']->email],
        ];
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
            'date_gel' => now()->subDays(4)->toDateString(),
        ]);

        $calendrier = [
            1 => [now()->subDays(3), now()->addDays(10)],
            2 => [now()->addDays(11), now()->addDays(25)],
            3 => [now()->addDays(26), now()->addDays(45)],
        ];
        foreach ($calendrier as $numero => [$debut, $fin]) {
            app(ParametrerPhase::class)->handle($edition->phase($numero), ['date_debut' => $debut->toDateString(), 'date_fin' => $fin->toDateString()]);
        }

        app(LancerEdition::class)->handle($edition);
        $primaires = $edition->fresh()->phase(Phase::PRIMAIRES);
        app(GenererScrutins::class)->handle($primaires);
        app(OuvrirPhase::class)->handle($primaires);

        foreach ($primaires->scrutins()->get() as $scrutin) {
            app(ArreterListes::class)->handle($scrutin);
            app(PlanifierScrutin::class)->handle($scrutin->fresh(), now()->subMinutes(5), now()->addDays(7)->setTime(18, 0));
            app(OuvrirScrutin::class)->handle($scrutin->fresh());
        }

        return $edition->fresh();
    }
}
