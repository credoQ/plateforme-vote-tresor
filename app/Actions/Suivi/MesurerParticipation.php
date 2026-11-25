<?php

namespace App\Actions\Suivi;

use App\Enums\Scrutins\StatutCandidate;
use App\Models\Departement;
use App\Models\Phase;
use App\Models\Scrutin;
use App\Models\Structure;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Participation d'une phase, par scrutin, par département et par structure (module 08).
 *
 * Seuls les inscrits et les votants sont comptés : aucune voix n'est exposée avant la clôture (R46).
 * Un périmètre (structures du point focal, R4) restreint les inscrits pris en compte.
 *
 * Les comptages sur les inscriptions sont gardés en cache quelques secondes, par phase et par périmètre : le tableau
 * de bord se rafraîchit chaque minute chez chaque membre du comité, qui partagent ainsi le même calcul. Seuls des
 * tableaux sont mis en cache (Laravel ne restaure pas d'objets depuis le cache) ; les scrutins sont relus en direct.
 */
class MesurerParticipation
{
    public const FUSEAU = 'Africa/Porto-Novo';

    /**
     * Durée, en secondes, pendant laquelle les comptages sont réutilisés.
     */
    public const DUREE_CACHE = 30;

    /**
     * @param  list<int>|null  $perimetre
     * @return array{global: array{inscrits: int, votants: int, taux: float}, scrutins: Collection<int, array<string, mixed>>, zones: array{departements: array<string, mixed>, structures: array<string, mixed>}, structures: Collection<int, array<string, mixed>>}
     */
    public function handle(Phase $phase, ?array $perimetre = null): array
    {
        $comptages = $this->comptages($phase, $perimetre);
        $parScrutin = $comptages['scrutins'];

        $scrutins = Scrutin::where('phase_id', $phase->id)
            ->with('poleElectoral')
            ->withCount([
                'candidates as candidates_actives' => fn ($q) => $q->where('statut', StatutCandidate::Active->value),
                'candidates as candidates_desistees' => fn ($q) => $q->where('statut', StatutCandidate::Desactivee->value),
            ])
            ->orderBy('code')
            ->get()
            ->filter(fn (Scrutin $s): bool => $perimetre === null || isset($parScrutin[$s->id]))
            ->map(fn (Scrutin $s): array => [
                'scrutin' => $s,
                ...$this->taux($parScrutin[$s->id]['inscrits'] ?? 0, $parScrutin[$s->id]['votants'] ?? 0),
            ])
            ->values();

        return [
            'global' => $this->taux($comptages['total']['inscrits'], $comptages['total']['votants']),
            'scrutins' => $scrutins,
            'zones' => $this->zones($comptages['structures']),
            'structures' => collect($comptages['structures'])->map(fn (array $l): array => ['code' => $l['code'], 'libelle' => $l['libelle'], ...$this->taux($l['inscrits'], $l['votants'])]),
        ];
    }

    /**
     * Participation par zone de vote (R24, R25) : chaque électeur compte dans le département de sa structure s'il en
     * a un (Trésoreries départementales et communales, antennes), sinon dans sa structure électorale (un service avec
     * sa structure de rattachement). Deux groupes, l'un par pôle, triés par taux décroissant.
     *
     * @param  list<array{id: int, code: string, libelle: string, departement_id: int|null, niveau: int, structure_parente_id: int|null, inscrits: int, votants: int}>  $parStructure
     * @return array{departements: array{taux: float, inscrits: int, votants: int, lignes: Collection<int, array<string, mixed>>}, structures: array{taux: float, inscrits: int, votants: int, lignes: Collection<int, array<string, mixed>>}}
     */
    private function zones(array $parStructure): array
    {
        $departements = Departement::whereKey(array_filter(array_column($parStructure, 'departement_id')))->pluck('libelle', 'id');
        $parentes = Structure::whereKey(array_filter(array_column($parStructure, 'structure_parente_id')))->get(['id', 'code', 'libelle'])->keyBy('id');

        $zones = ['departements' => [], 'structures' => []];
        foreach ($parStructure as $ligne) {
            if ($ligne['departement_id'] !== null) {
                $groupe = 'departements';
                $cle = $ligne['departement_id'];
                $zone = ['code' => $departements[$cle] ?? '', 'libelle' => $departements[$cle] ?? ''];
            } else {
                $electorale = $ligne['niveau'] === Structure::NIVEAU_STRUCTURE_ELECTORALE ? null : $parentes[$ligne['structure_parente_id']] ?? null;
                $groupe = 'structures';
                $cle = $electorale?->id ?? $ligne['id'];
                $zone = ['code' => $electorale?->code ?? $ligne['code'], 'libelle' => $electorale?->libelle ?? $ligne['libelle']];
            }

            $zones[$groupe][$cle] ??= [...$zone, 'inscrits' => 0, 'votants' => 0];
            $zones[$groupe][$cle]['inscrits'] += $ligne['inscrits'];
            $zones[$groupe][$cle]['votants'] += $ligne['votants'];
        }

        return array_map(fn (array $lignes): array => [
            ...$this->taux(array_sum(array_column($lignes, 'inscrits')), array_sum(array_column($lignes, 'votants'))),
            'lignes' => collect($lignes)
                ->map(fn (array $l): array => ['code' => $l['code'], 'libelle' => $l['libelle'], ...$this->taux($l['inscrits'], $l['votants'])])
                ->sortBy([['taux', 'desc'], ['code', 'asc']])
                ->values(),
        ], $zones);
    }

    /**
     * Votes par heure du dernier jour de vote de la phase, à l'heure du Bénin. L'heure d'émargement étant tronquée
     * à l'heure (R43), ce décompte ne dit rien de plus que la participation (R46).
     *
     * @param  list<int>|null  $perimetre
     * @return array{jour: CarbonImmutable|null, heures: array<int, int>}
     */
    public function votesParHeure(Phase $phase, ?array $perimetre = null): array
    {
        $lignes = Cache::memo()->remember($this->cle('votes-par-heure', $phase, $perimetre), self::DUREE_CACHE, fn (): array => DB::table('inscriptions')
            ->where('phase_id', $phase->id)
            ->where('a_vote', true)
            ->whereNotNull('date_heure_vote')
            ->when($perimetre !== null, fn ($q) => $q->whereIn('structure_id', $perimetre))
            ->groupBy('date_heure_vote')
            ->selectRaw('date_heure_vote, count(*) as votes')
            ->get()
            ->map(fn ($ligne): array => [(string) $ligne->date_heure_vote, (int) $ligne->votes])
            ->all());

        $parHeure = collect($lignes)->map(fn (array $ligne): array => [CarbonImmutable::parse($ligne[0], 'UTC')->setTimezone(self::FUSEAU), $ligne[1]]);

        if ($parHeure->isEmpty()) {
            return ['jour' => null, 'heures' => []];
        }

        $jour = $parHeure->max(fn (array $ligne): CarbonImmutable => $ligne[0])->startOfDay();
        $duJour = $parHeure->filter(fn (array $ligne): bool => $ligne[0]->isSameDay($jour));
        $heures = [];
        foreach (range($duJour->min(fn (array $l): int => $l[0]->hour), $duJour->max(fn (array $l): int => $l[0]->hour)) as $heure) {
            $heures[$heure] = 0;
        }
        foreach ($duJour as [$moment, $votes]) {
            $heures[$moment->hour] += $votes;
        }

        return ['jour' => $jour, 'heures' => $heures];
    }

    /**
     * Inscrits et votants de la phase, par scrutin, par structure et au total.
     *
     * @param  list<int>|null  $perimetre
     * @return array{scrutins: array<int, array{inscrits: int, votants: int}>, structures: list<array{id: int, code: string, libelle: string, departement_id: int|null, niveau: int, structure_parente_id: int|null, inscrits: int, votants: int}>, total: array{inscrits: int, votants: int}}
     */
    private function comptages(Phase $phase, ?array $perimetre): array
    {
        return Cache::memo()->remember($this->cle('participation', $phase, $perimetre), self::DUREE_CACHE, function () use ($phase, $perimetre): array {
            $inscriptions = fn () => DB::table('inscriptions')
                ->where('inscriptions.phase_id', $phase->id)
                ->when($perimetre !== null, fn ($q) => $q->whereIn('inscriptions.structure_id', $perimetre));

            $compter = 'count(*) as inscrits, count(*) filter (where inscriptions.a_vote) as votants';
            $nombres = fn ($ligne): array => ['inscrits' => (int) $ligne->inscrits, 'votants' => (int) $ligne->votants];
            $total = $inscriptions()->selectRaw($compter)->first();

            return [
                'scrutins' => $inscriptions()->groupBy('scrutin_id')->selectRaw("scrutin_id, {$compter}")->get()
                    ->mapWithKeys(fn ($ligne): array => [(int) $ligne->scrutin_id => $nombres($ligne)])->all(),
                'structures' => $inscriptions()
                    ->join('structures', 'structures.id', '=', 'inscriptions.structure_id')
                    ->groupBy('structures.id', 'structures.code', 'structures.libelle', 'structures.departement_id', 'structures.niveau', 'structures.structure_parente_id')
                    ->orderBy('structures.code')
                    ->selectRaw("structures.id, structures.code, structures.libelle, structures.departement_id, structures.niveau, structures.structure_parente_id, {$compter}")
                    ->get()
                    ->map(fn ($ligne): array => [
                        'id' => (int) $ligne->id,
                        'code' => $ligne->code,
                        'libelle' => $ligne->libelle,
                        'departement_id' => $ligne->departement_id !== null ? (int) $ligne->departement_id : null,
                        'niveau' => (int) $ligne->niveau,
                        'structure_parente_id' => $ligne->structure_parente_id !== null ? (int) $ligne->structure_parente_id : null,
                        ...$nombres($ligne),
                    ])->all(),
                'total' => $nombres($total),
            ];
        });
    }

    /**
     * Clé de cache propre à une phase et à un périmètre (toute la DGTCP, ou les structures d'un point focal).
     *
     * @param  list<int>|null  $perimetre
     */
    private function cle(string $mesure, Phase $phase, ?array $perimetre): string
    {
        return "suivi.{$mesure}.{$phase->id}.".($perimetre === null ? 'dgtcp' : md5(implode(',', $perimetre)));
    }

    /**
     * @return array{inscrits: int, votants: int, taux: float}
     */
    private function taux(int $inscrits, int $votants): array
    {
        return ['inscrits' => $inscrits, 'votants' => $votants, 'taux' => $inscrits === 0 ? 0.0 : round($votants * 100 / $inscrits, 1)];
    }
}
