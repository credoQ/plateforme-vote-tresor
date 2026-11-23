<?php

namespace Database\Seeders;

use App\Enums\Acces\CodeRole;
use App\Enums\Editions\SourceFemmeModele;
use App\Models\Agent;
use App\Models\FemmeModele;
use App\Models\Role;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Données de test, sans édition : le référentiel, des agents dans quelques structures, leurs comptes (électeurs,
 * comité, point focal, administrateur, observateur) et une femme modèle d'il y a deux ans, inéligible.
 * À lancer sur une base sans agents : php artisan db:seed --class=DonneesDeTestSeeder
 *
 * Les agents sont créés par les factories : Faker doit être installé (dépendance de développement).
 */
class DonneesDeTestSeeder extends Seeder
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

        if (Agent::exists()) {
            throw new RuntimeException('La base contient déjà des agents : les données de test se chargent sur une base sans agents.');
        }

        $comptes = DB::transaction(function (): array {
            $agents = $this->creerAgents();
            $comptes = $this->creerComptes($agents);

            // Femme modèle il y a deux ans, saisie : inéligible cette année avec le délai par défaut de 3 ans.
            $florence = $agents['GBAGUIDI Florence'];
            FemmeModele::create(['matricule' => $florence->matricule, 'agent_id' => $florence->id, 'nom_complet' => $florence->nomComplet(), 'annee' => (int) now()->year - 2, 'scrutin' => 'Finale', 'source' => SourceFemmeModele::Saisie]);

            return $comptes;
        });

        $this->command?->table(['Profil', 'Identifiant (login)', 'Adresse e-mail'], $comptes);
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
            ['Électrice DIA (femme modèle, inéligible)', $agents['GBAGUIDI Florence']->matricule, $agents['GBAGUIDI Florence']->email],
            ['Électrice Atlantique (candidate)', $agents['HOUNSOU Afiavi']->matricule, $agents['HOUNSOU Afiavi']->email],
            ['Électeur Atlantique', $agents['DEGBE Jean']->matricule, $agents['DEGBE Jean']->email],
            ['Électeur Borgou (sans scrutin)', $agents['BIO Ibrahim']->matricule, $agents['BIO Ibrahim']->email],
        ];
    }
}
