<?php

namespace App\Actions\Suivi;

use App\Enums\Scrutins\StatutCandidate;
use App\Models\Phase;
use App\Models\Scrutin;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Participation d'une phase, par scrutin, par département et par structure (module 08).
 *
 * Seuls les inscrits et les votants sont comptés : aucune voix n'est exposée avant la clôture (R46).
 * Un périmètre (structures du point focal, R4) restreint les inscrits pris en compte.
 */
class MesurerParticipation
{
    public const FUSEAU = 'Africa/Porto-Novo';

    /**
     * @param  list<int>|null  $perimetre
     * @return array{global: array{inscrits: int, votants: int, taux: float}, scrutins: Collection<int, array<string, mixed>>, departements: Collection<int, array<string, mixed>>, structures: Collection<int, array<string, mixed>>}
     */
    public function handle(Phase $phase, ?array $perimetre = null): array
    {
        $inscriptions = fn () => DB::table('inscriptions')
            ->where('inscriptions.phase_id', $phase->id)
            ->when($perimetre !== null, fn ($q) => $q->whereIn('inscriptions.structure_id', $perimetre));

        $compter = 'count(*) as inscrits, count(*) filter (where inscriptions.a_vote) as votants';

        $parScrutin = $inscriptions()->groupBy('scrutin_id')->selectRaw("scrutin_id, {$compter}")->get()->keyBy('scrutin_id');

        $scrutins = Scrutin::where('phase_id', $phase->id)
            ->with('poleElectoral')
            ->withCount([
                'candidates as candidates_actives' => fn ($q) => $q->where('statut', StatutCandidate::Active->value),
                'candidates as candidates_desistees' => fn ($q) => $q->where('statut', StatutCandidate::Desactivee->value),
            ])
            ->orderBy('code')
            ->get()
            ->filter(fn (Scrutin $s): bool => $perimetre === null || $parScrutin->has($s->id))
            ->map(fn (Scrutin $s): array => [
                'scrutin' => $s,
                ...$this->taux((int) ($parScrutin[$s->id]->inscrits ?? 0), (int) ($parScrutin[$s->id]->votants ?? 0)),
            ])
            ->values();

        $departements = $inscriptions()
            ->join('structures', 'structures.id', '=', 'inscriptions.structure_id')
            ->join('departements', 'departements.id', '=', 'structures.departement_id')
            ->groupBy('departements.id', 'departements.libelle')
            ->orderBy('departements.libelle')
            ->selectRaw("departements.libelle, {$compter}")
            ->get()
            ->map(fn ($ligne): array => ['libelle' => $ligne->libelle, ...$this->taux((int) $ligne->inscrits, (int) $ligne->votants)]);

        $structures = $inscriptions()
            ->join('structures', 'structures.id', '=', 'inscriptions.structure_id')
            ->groupBy('structures.id', 'structures.code', 'structures.libelle')
            ->orderBy('structures.code')
            ->selectRaw("structures.code, structures.libelle, {$compter}")
            ->get()
            ->map(fn ($ligne): array => ['code' => $ligne->code, 'libelle' => $ligne->libelle, ...$this->taux((int) $ligne->inscrits, (int) $ligne->votants)]);

        $total = $inscriptions()->selectRaw($compter)->first();

        return [
            'global' => $this->taux((int) $total->inscrits, (int) $total->votants),
            'scrutins' => $scrutins,
            'departements' => $departements,
            'structures' => $structures,
        ];
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
        $parHeure = DB::table('inscriptions')
            ->where('phase_id', $phase->id)
            ->where('a_vote', true)
            ->whereNotNull('date_heure_vote')
            ->when($perimetre !== null, fn ($q) => $q->whereIn('structure_id', $perimetre))
            ->groupBy('date_heure_vote')
            ->selectRaw('date_heure_vote, count(*) as votes')
            ->get()
            ->map(fn ($ligne): array => [CarbonImmutable::parse($ligne->date_heure_vote, 'UTC')->setTimezone(self::FUSEAU), (int) $ligne->votes]);

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
     * @return array{inscrits: int, votants: int, taux: float}
     */
    private function taux(int $inscrits, int $votants): array
    {
        return ['inscrits' => $inscrits, 'votants' => $votants, 'taux' => $inscrits === 0 ? 0.0 : round($votants * 100 / $inscrits, 1)];
    }
}
