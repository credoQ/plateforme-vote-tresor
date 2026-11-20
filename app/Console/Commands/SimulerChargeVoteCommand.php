<?php

namespace App\Console\Commands;

use App\Actions\Resultats\DepouillerScrutin;
use App\Actions\Scrutins\ArreterListes;
use App\Actions\Scrutins\FermerScrutin;
use App\Actions\Scrutins\GenererScrutins;
use App\Actions\Scrutins\OuvrirScrutin;
use App\Actions\Scrutins\PlanifierScrutin;
use App\Actions\Vote\DeposerBulletin;
use App\Actions\Vote\EmpreinteBulletin;
use App\Enums\Editions\StatutPhase;
use App\Models\Agent;
use App\Models\Candidate;
use App\Models\Edition;
use App\Models\Inscription;
use App\Models\Phase;
use App\Models\Scrutin;
use App\Models\Structure;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;

/**
 * Test de charge du vote (R40, R44) : un seul scrutin de N électeurs, dont les votes passent par l'action réelle
 * DeposerBulletin, en plusieurs processus parallèles pour reproduire le pic d'ouverture. Mesure la durée de chaque
 * vote, puis la vérification de la chaîne et le dépouillement.
 *
 * Les bulletins ne peuvent jamais être supprimés : la commande exige une base dédiée, sans aucune édition, et refuse
 * de tourner en production. Elle mesure la base de données et le code métier, pas la couche HTTP (sessions, réseau).
 */
#[Signature('vote:simuler-charge
    {--electeurs=2000 : Nombre d’électeurs du scrutin, candidates comprises}
    {--candidates=10 : Nombre de candidates}
    {--paralleles=4 : Nombre de processus qui votent en même temps}
    {--force : Ne pas demander de confirmation}')]
#[Description('Simule un scrutin de N électeurs sur une base dédiée et mesure la durée des votes, de la vérification de la chaîne et du dépouillement')]
class SimulerChargeVoteCommand extends Command
{
    public function handle(): int
    {
        $electeurs = (int) $this->option('electeurs');
        $candidates = (int) $this->option('candidates');
        $paralleles = max(1, (int) $this->option('paralleles'));

        if (app()->isProduction()) {
            $this->components->error('Cette commande ne tourne jamais en production.');

            return self::FAILURE;
        }

        if ($candidates < 2 || $electeurs < $candidates) {
            $this->components->error('Il faut au moins deux candidates, et au moins autant d’électeurs que de candidates.');

            return self::FAILURE;
        }

        if (Edition::query()->exists()) {
            $this->components->error('La base contient déjà une édition. Lancez la simulation sur une base dédiée, vide d’éditions (migrate:fresh --seed).');

            return self::FAILURE;
        }

        $base = DB::connection()->getDatabaseName();
        if (! $this->option('force') && ! $this->confirm("Les bulletins créés dans la base « {$base} » ne pourront plus être supprimés. Continuer ?")) {
            return self::FAILURE;
        }

        $scrutin = $this->preparerScrutin($electeurs, $candidates);
        $choix = Candidate::where('scrutin_id', $scrutin->id)->pluck('agent_id', 'id')->all();
        $tranches = Inscription::where('scrutin_id', $scrutin->id)->inRandomOrder()->pluck('agent_id')
            ->split($paralleles)->map(fn (Collection $tranche): array => $tranche->all())->all();

        $this->components->info("Vote de {$electeurs} électeurs, en {$paralleles} processus parallèle(s)…");
        $debut = hrtime(true);
        $durees = $this->voter($scrutin->id, $tranches, $choix);
        $total = (hrtime(true) - $debut) / 1e9;

        $this->afficherVotes($durees, $total);
        $this->mesurerCloture($scrutin);

        return self::SUCCESS;
    }

    /**
     * Fait voter une tranche d'électeurs et renvoie la durée de chaque vote, en millisecondes, dans l'ordre.
     * Statique : elle est sérialisée et exécutée dans un processus séparé.
     *
     * @param  array<int, int>  $agents
     * @param  array<int, int>  $choix  candidate_id => agent_id
     * @return array<int, float>
     */
    public static function voterTranche(int $scrutinId, array $agents, array $choix): array
    {
        $deposer = app(DeposerBulletin::class);
        $scrutin = Scrutin::findOrFail($scrutinId);
        $candidates = Candidate::whereIn('id', array_keys($choix))->get()->keyBy('id');
        $durees = [];

        foreach (Agent::whereIn('id', $agents)->get() as $agent) {
            $autres = array_keys(array_filter($choix, fn (int $agentId): bool => $agentId !== $agent->id));
            $candidate = $candidates[$autres[array_rand($autres)]];

            $debut = hrtime(true);
            $deposer->handle($agent, $scrutin, $candidate);
            $durees[] = (hrtime(true) - $debut) / 1e6;
        }

        return $durees;
    }

    /**
     * Édition en cours, agents de la DIA (femmes candidates d'office, hommes électeurs), scrutin de structure
     * généré, listes arrêtées et scrutin ouvert, par les actions réelles.
     */
    private function preparerScrutin(int $electeurs, int $candidates): Scrutin
    {
        $dia = Structure::where('code', 'DIA')->first()
            ?? throw new \RuntimeException('Structure DIA absente : chargez le référentiel (db:seed).');
        $depuis = Carbon::parse('2020-01-01');

        $this->components->task("Création de {$electeurs} agents", function () use ($dia, $depuis, $electeurs, $candidates): void {
            DB::transaction(function () use ($dia, $depuis, $electeurs, $candidates): void {
                Agent::factory()->count($candidates)->femme()->affecteA($dia, $depuis)->create();
                Agent::factory()->count($electeurs - $candidates)->homme()->affecteA($dia, $depuis)->create();
            });
        });

        $edition = Edition::factory()->avecPhases()->enCours()->create([
            'annee' => now()->year, 'libelle' => 'Simulation de charge',
            'date_debut' => now()->subDay()->toDateString(), 'date_fin' => now()->addMonth()->toDateString(), 'date_gel' => now()->subDay()->toDateString(),
        ]);
        $phase = $edition->phase(Phase::PRIMAIRES);
        app(GenererScrutins::class)->handle($phase);
        $phase->update(['statut' => StatutPhase::Ouverte]);

        $scrutin = Scrutin::findOrFail(Inscription::query()->where('phase_id', $phase->id)->value('scrutin_id'));
        app(ArreterListes::class)->handle($scrutin);
        app(PlanifierScrutin::class)->handle($scrutin->fresh(), now()->subMinute(), now()->addHours(6));

        return app(OuvrirScrutin::class)->handle($scrutin->fresh());
    }

    /**
     * @param  array<int, array<int, int>>  $tranches
     * @param  array<int, int>  $choix
     * @return array<int, array<int, float>>
     */
    private function voter(int $scrutinId, array $tranches, array $choix): array
    {
        if (count($tranches) === 1) {
            return [self::voterTranche($scrutinId, $tranches[0], $choix)];
        }

        $taches = [];
        foreach ($tranches as $tranche) {
            $taches[] = self::tache($scrutinId, $tranche, $choix);
        }

        return Concurrency::driver('process')->run($taches);
    }

    /**
     * Tâche exécutée dans un processus séparé. Une méthode à part : le pilote process sérialise la closure
     * en relisant son code, ce qui échoue sur des closures imbriquées.
     *
     * @param  array<int, int>  $agents
     * @param  array<int, int>  $choix
     */
    private static function tache(int $scrutinId, array $agents, array $choix): \Closure
    {
        return static function () use ($scrutinId, $agents, $choix): array {
            return SimulerChargeVoteCommand::voterTranche($scrutinId, $agents, $choix);
        };
    }

    /**
     * Durées globales, puis comparaison des premiers et des derniers votes de chaque processus : si elles restent
     * proches, la durée d'un vote ne dépend pas du nombre de bulletins déjà déposés.
     *
     * @param  array<int, array<int, float>>  $durees
     */
    private function afficherVotes(array $durees, float $total): void
    {
        $toutes = collect($durees)->flatten()->sort()->values();
        $dixieme = fn (array $tranche, bool $debut): array => $debut
            ? array_slice($tranche, 0, max(1, intdiv(count($tranche), 10)))
            : array_slice($tranche, -max(1, intdiv(count($tranche), 10)));
        $premiers = collect($durees)->flatMap(fn (array $tranche): array => $dixieme($tranche, true));
        $derniers = collect($durees)->flatMap(fn (array $tranche): array => $dixieme($tranche, false));
        $ms = fn (float $valeur): string => number_format($valeur, 1, ',', ' ').' ms';

        $this->table(['Mesure', 'Valeur'], [
            ['Votes enregistrés', number_format($toutes->count(), 0, ',', ' ')],
            ['Durée totale', number_format($total, 1, ',', ' ').' s'],
            ['Débit', number_format($toutes->count() / max($total, 0.001), 1, ',', ' ').' votes/s'],
            ['Durée moyenne d’un vote', $ms($toutes->avg())],
            ['Médiane', $ms($toutes->median())],
            ['95e centile', $ms($toutes[(int) floor(0.95 * ($toutes->count() - 1))])],
            ['Maximum', $ms($toutes->max())],
            ['Moyenne des 10 % premiers votes', $ms($premiers->avg())],
            ['Moyenne des 10 % derniers votes', $ms($derniers->avg())],
        ]);
    }

    private function mesurerCloture(Scrutin $scrutin): void
    {
        $debut = hrtime(true);
        $intacte = app(EmpreinteBulletin::class)->chaineEstIntacte($scrutin->id);
        $verification = (hrtime(true) - $debut) / 1e6;

        // Base de simulation : la fermeture est avancée à maintenant pour dépouiller sans attendre.
        DB::table('scrutins')->where('id', $scrutin->id)->update(['date_heure_fermeture' => now()->subSecond()]);
        app(FermerScrutin::class)->handle($scrutin);
        $debut = hrtime(true);
        $pv = app(DepouillerScrutin::class)->handle($scrutin);
        $depouillement = (hrtime(true) - $debut) / 1e6;

        $this->table(['Clôture', 'Valeur'], [
            ['Chaîne des empreintes', $intacte ? 'intacte' : 'ROMPUE'],
            ['Vérification de la chaîne', number_format($verification, 0, ',', ' ').' ms'],
            ['Dépouillement', number_format($depouillement, 0, ',', ' ').' ms'],
            ['Votants au procès-verbal', number_format($pv->nb_votants, 0, ',', ' ')],
        ]);
    }
}
