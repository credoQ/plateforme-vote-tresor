<?php

namespace App\Actions\Suivi;

use App\Enums\Scrutins\StatutCandidate;
use App\Models\Phase;
use App\Models\Scrutin;
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
            ->withCount(['candidates as candidates_actives' => fn ($q) => $q->where('statut', StatutCandidate::Active->value)])
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
     * @return array{inscrits: int, votants: int, taux: float}
     */
    private function taux(int $inscrits, int $votants): array
    {
        return ['inscrits' => $inscrits, 'votants' => $votants, 'taux' => $inscrits === 0 ? 0.0 : round($votants * 100 / $inscrits, 1)];
    }
}
