<?php

namespace App\Livewire\Suivi;

use App\Actions\Suivi\MesurerParticipation;
use App\Enums\Acces\CodeRole;
use App\Enums\Editions\StatutEdition;
use App\Enums\Editions\StatutPhase;
use App\Enums\Referentiel\CodePoleElectoral;
use App\Enums\Scrutins\StatutScrutin;
use App\Models\Edition;
use App\Models\Phase;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Suivi de la participation d'une phase de l'édition en cours (module 08) : comité, observateur, administrateur,
 * et point focal limité à sa structure (R4). Rafraîchi toutes les minutes pendant le vote ; aucune voix n'est
 * affichée (R46).
 */
class TableauDeBord extends Component
{
    use WithPagination;

    private const SCRUTINS_PAR_PAGE = 10;

    #[Url]
    public string $phase = '';

    #[Url]
    public string $recherche = '';

    #[Url]
    public string $pole = '';

    #[Url]
    public string $statut = '';

    /**
     * Contrôle des droits à chaque requête du composant, rafraîchissements compris.
     */
    public function boot(): void
    {
        abort_unless($this->utilisateur()->can('observer') || $this->utilisateur()->can('controler-listes'), 403);
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function choisirPhase(int $numero): void
    {
        $this->phase = (string) $numero;
        $this->resetPage();
    }

    #[Computed]
    public function edition(): ?Edition
    {
        return Edition::with('phases')->where('statut', StatutEdition::EnCours->value)->first()
            ?? Edition::with('phases')->orderByDesc('annee')->first();
    }

    /**
     * Phase demandée, sinon la phase ouverte, la dernière clôturée ou la première.
     */
    #[Computed]
    public function phaseAffichee(): ?Phase
    {
        $phases = $this->edition?->phases;

        return ((int) $this->phase > 0 ? $phases?->firstWhere('numero', (int) $this->phase) : null)
            ?? $phases?->firstWhere('statut', StatutPhase::Ouverte)
            ?? $phases?->where('statut', StatutPhase::Cloturee)->last()
            ?? $phases?->first();
    }

    /**
     * Structures du point focal, ou null pour les profils qui suivent toute la DGTCP.
     *
     * @return list<int>|null
     */
    #[Computed]
    public function limite(): ?array
    {
        $utilisateur = $this->utilisateur();

        return $utilisateur->aLeRole(CodeRole::Comite, CodeRole::Observateur, CodeRole::Administrateur) ? null : $utilisateur->perimetre();
    }

    /**
     * @return array{global: array{inscrits: int, votants: int, taux: float}, scrutins: Collection<int, array<string, mixed>>, departements: Collection<int, array<string, mixed>>, structures: Collection<int, array<string, mixed>>}|null
     */
    #[Computed]
    public function participation(): ?array
    {
        return $this->phaseAffichee === null ? null : app(MesurerParticipation::class)->handle($this->phaseAffichee, $this->limite);
    }

    public function paginationView(): string
    {
        return 'pagination.tresor-livewire';
    }

    public function render(): View
    {
        $mesurer = app(MesurerParticipation::class);
        $scrutins = $this->participation['scrutins'] ?? collect();

        return view('livewire.suivi.tableau-de-bord', [
            'edition' => $this->edition,
            'phaseAffichee' => $this->phaseAffichee,
            'participation' => $this->participation,
            'global' => $this->participation['global'] ?? null,
            'indicateurs' => $this->indicateurs($scrutins),
            'heures' => $this->phaseAffichee === null ? null : $this->heures($mesurer->votesParHeure($this->phaseAffichee, $this->limite)),
            'departements' => ($this->participation['departements'] ?? collect())->sortByDesc('taux')->values(),
            'scrutins' => $this->paginer($this->filtrer($scrutins)),
            'poles' => $this->poles(),
            'filtresActifs' => trim($this->recherche) !== '' || $this->pole !== '' || $this->statut !== '',
            'restreint' => $this->limite !== null,
            'misAJourLe' => now()->timezone('Africa/Porto-Novo'),
        ]);
    }

    private function utilisateur(): User
    {
        /** @var User */
        return Auth::user();
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
    private function filtrer(Collection $scrutins): Collection
    {
        $terme = mb_strtolower(trim($this->recherche));
        $statut = StatutScrutin::tryFrom($this->statut);

        return $scrutins
            ->when($terme !== '', fn (Collection $c) => $c->filter(fn (array $l): bool => str_contains(mb_strtolower($l['scrutin']->libelle.' '.$l['scrutin']->code), $terme)))
            ->when($this->pole !== '', fn (Collection $c) => $c->filter(fn (array $l): bool => ($l['scrutin']->poleElectoral?->code->value ?? Phase::COLLEGE_DGTCP) === $this->pole))
            ->when($statut !== null, fn (Collection $c) => $c->filter(fn (array $l): bool => $l['scrutin']->statut === $statut))
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $scrutins
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function paginer(Collection $scrutins): LengthAwarePaginator
    {
        $page = $this->getPage();

        return new LengthAwarePaginator(
            $scrutins->forPage($page, self::SCRUTINS_PAR_PAGE)->values(),
            $scrutins->count(),
            self::SCRUTINS_PAR_PAGE,
            $page,
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
