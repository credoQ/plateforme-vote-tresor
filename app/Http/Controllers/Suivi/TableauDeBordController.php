<?php

namespace App\Http\Controllers\Suivi;

use App\Actions\Suivi\MesurerParticipation;
use App\Enums\Acces\CodeRole;
use App\Enums\Editions\StatutEdition;
use App\Enums\Editions\StatutPhase;
use App\Enums\Referentiel\CodePoleElectoral;
use App\Enums\Scrutins\StatutScrutin;
use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\Phase;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Suivi de la participation d'une phase de l'édition en cours (module 08) : comité, observateur, administrateur,
 * et point focal limité à sa structure (R4). Aucune voix n'est affichée (R46).
 */
class TableauDeBordController extends Controller
{
    private const SCRUTINS_PAR_PAGE = 10;

    public function __invoke(Request $request, MesurerParticipation $mesurer): View
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur->can('observer') || $utilisateur->can('controler-listes'), 403);

        $edition = Edition::with('phases')->where('statut', StatutEdition::EnCours->value)->first()
            ?? Edition::with('phases')->orderByDesc('annee')->first();

        $phase = $this->phase($edition, $request->integer('phase') ?: null);
        $limite = $utilisateur->aLeRole(CodeRole::Comite, CodeRole::Observateur, CodeRole::Administrateur) ? null : $utilisateur->perimetre();

        $participation = $phase === null ? null : $mesurer->handle($phase, $limite);
        $scrutins = $participation['scrutins'] ?? collect();

        $recherche = trim((string) $request->query('recherche', ''));
        $pole = (string) $request->query('pole', '');
        $statut = StatutScrutin::tryFrom((string) $request->query('statut', ''));

        return view('suivi.tableau-de-bord', [
            'edition' => $edition,
            'phase' => $phase,
            'participation' => $participation,
            'global' => $participation['global'] ?? null,
            'indicateurs' => $this->indicateurs($scrutins),
            'heures' => $phase === null ? null : $this->heures($mesurer->votesParHeure($phase, $limite)),
            'departements' => ($participation['departements'] ?? collect())->sortByDesc('taux')->values(),
            'scrutins' => $this->paginer($this->filtrer($scrutins, $recherche, $pole, $statut), $request),
            'recherche' => $recherche,
            'pole' => $pole,
            'poles' => $this->poles(),
            'statut' => $statut,
            'restreint' => $limite !== null,
        ]);
    }

    /**
     * Phase demandée, sinon la phase ouverte, la dernière clôturée ou la première.
     */
    private function phase(?Edition $edition, ?int $numero): ?Phase
    {
        return ($numero !== null ? $edition?->phases->firstWhere('numero', $numero) : null)
            ?? $edition?->phases->firstWhere('statut', StatutPhase::Ouverte)
            ?? $edition?->phases->where('statut', StatutPhase::Cloturee)->last()
            ?? $edition?->phases->first();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $scrutins
     * @return array{total: int, ouverts: int, termines: int, a_venir: int, candidates: int, desistements: int, fermeture: Carbon|null, restant: string|null}
     */
    private function indicateurs(Collection $scrutins): array
    {
        $statuts = $scrutins->map(fn (array $ligne): StatutScrutin => $ligne['scrutin']->statut);
        $fermeture = $scrutins->filter(fn (array $ligne): bool => $ligne['scrutin']->statut === StatutScrutin::Ouvert)
            ->map(fn (array $ligne) => $ligne['scrutin']->date_heure_fermeture)->filter()->max();

        return [
            'total' => $scrutins->count(),
            'ouverts' => $statuts->filter(fn (StatutScrutin $s): bool => $s === StatutScrutin::Ouvert)->count(),
            'termines' => $statuts->filter(fn (StatutScrutin $s): bool => $s->estTermine())->count(),
            'a_venir' => $statuts->filter(fn (StatutScrutin $s): bool => in_array($s, [StatutScrutin::EnPreparation, StatutScrutin::ListesArretees], true))->count(),
            'candidates' => $scrutins->sum(fn (array $ligne): int => (int) $ligne['scrutin']->candidates_actives),
            'desistements' => $scrutins->sum(fn (array $ligne): int => (int) $ligne['scrutin']->candidates_desistees),
            'fermeture' => $fermeture,
            'restant' => $fermeture === null || $fermeture->isPast() ? null : $this->duree(now()->diffInMinutes($fermeture)),
        ];
    }

    /**
     * Barres du graphique « Votes par heure » : libellé, nombre de votes et hauteur relative au maximum.
     *
     * @param  array{jour: CarbonImmutable|null, heures: array<int, int>}  $votes
     * @return array{jour: CarbonImmutable|null, barres: list<array{heure: string, plage: string, votes: int, hauteur: float, maximum: bool}>}
     */
    private function heures(array $votes): array
    {
        $maximum = max([1, ...array_values($votes['heures'])]);

        return [
            'jour' => $votes['jour'],
            'barres' => collect($votes['heures'])->map(fn (int $nombre, int $heure): array => [
                'heure' => $heure.' h',
                'plage' => 'De '.$heure.' h à '.($heure + 1).' h',
                'votes' => $nombre,
                'hauteur' => round($nombre * 100 / $maximum, 1),
                'maximum' => $nombre === $maximum,
            ])->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $scrutins
     * @return Collection<int, array<string, mixed>>
     */
    private function filtrer(Collection $scrutins, string $recherche, string $pole, ?StatutScrutin $statut): Collection
    {
        $terme = mb_strtolower($recherche);

        return $scrutins
            ->when($terme !== '', fn (Collection $c) => $c->filter(fn (array $l): bool => str_contains(mb_strtolower($l['scrutin']->libelle.' '.$l['scrutin']->code), $terme)))
            ->when($pole !== '', fn (Collection $c) => $c->filter(fn (array $l): bool => ($l['scrutin']->poleElectoral?->code->value ?? Phase::COLLEGE_DGTCP) === $pole))
            ->when($statut !== null, fn (Collection $c) => $c->filter(fn (array $l): bool => $l['scrutin']->statut === $statut))
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $scrutins
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function paginer(Collection $scrutins, Request $request): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $scrutins->forPage($page, self::SCRUTINS_PAR_PAGE)->values(),
            $scrutins->count(),
            self::SCRUTINS_PAR_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }

    /**
     * « 2 j 6 h », « 5 h 20 min » ou « 12 min ».
     */
    private function duree(int|float $minutes): string
    {
        $minutes = (int) $minutes;
        $jours = intdiv($minutes, 1440);
        $heures = intdiv($minutes % 1440, 60);

        return match (true) {
            $jours > 0 => "{$jours} j {$heures} h",
            $heures > 0 => "{$heures} h ".($minutes % 60).' min',
            default => "{$minutes} min",
        };
    }

    /**
     * Pôles proposés par le filtre des scrutins.
     *
     * @return array<string, string>
     */
    private function poles(): array
    {
        return [
            CodePoleElectoral::Departemental->value => CodePoleElectoral::Departemental->libelle(),
            CodePoleElectoral::Structures->value => CodePoleElectoral::Structures->libelle(),
            Phase::COLLEGE_DGTCP => 'Toute la DGTCP',
        ];
    }
}
