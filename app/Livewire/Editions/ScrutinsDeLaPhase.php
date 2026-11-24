<?php

namespace App\Livewire\Editions;

use App\Enums\Referentiel\CodePoleElectoral;
use App\Enums\Scrutins\StatutScrutin;
use App\Models\Phase;
use App\Models\Scrutin;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Onglets de la phase choisie sur la fiche d'une édition (module 04) : ses scrutins, ses périmètres et ses
 * résultats à valider. Changer d'onglet, filtrer ou paginer ne met à jour que cette zone, sans recharger la page.
 */
class ScrutinsDeLaPhase extends Component
{
    use WithPagination;

    /**
     * Onglets de la zone.
     */
    public const ONGLETS = ['scrutins', 'colleges', 'resultats'];

    public Phase $phase;

    #[Url]
    public string $onglet = 'scrutins';

    #[Url]
    public string $recherche = '';

    #[Url]
    public string $college = '';

    #[Url]
    public string $statut = '';

    /**
     * Contrôle des droits à chaque requête du composant, mises à jour comprises.
     */
    public function boot(): void
    {
        Gate::authorize('piloter');
    }

    public function mount(): void
    {
        if (! in_array($this->onglet, self::ONGLETS, true)) {
            $this->onglet = 'scrutins';
        }
    }

    public function choisirOnglet(string $onglet): void
    {
        $this->onglet = in_array($onglet, self::ONGLETS, true) ? $onglet : 'scrutins';
        $this->statut = '';
        $this->resetPage();
    }

    public function updated(string $propriete): void
    {
        if (in_array($propriete, ['recherche', 'college', 'statut'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Nombre de scrutins, de périmètres et de résultats à valider, affichés sur les onglets.
     *
     * @return array{scrutins: int, colleges: int, resultats: int}
     */
    #[Computed]
    public function compteurs(): array
    {
        return [
            'scrutins' => $this->phase->scrutins()->count(),
            'colleges' => $this->phase->colleges()->count(),
            'resultats' => $this->phase->scrutins()->whereIn('statut', [StatutScrutin::Clos->value, StatutScrutin::ResultatsValides->value])->count(),
        ];
    }

    /**
     * Scrutins de l'onglet, filtrés, avec leurs inscrits, leurs candidates actives et leur participation.
     *
     * @return LengthAwarePaginator<int, array{scrutin: Scrutin, taux: float|null}>
     */
    #[Computed]
    public function scrutins(): LengthAwarePaginator
    {
        $recherche = trim($this->recherche);
        $statut = StatutScrutin::tryFrom($this->statut);
        $college = $this->college;

        return $this->phase->scrutins()
            ->withCount([
                'inscriptions',
                'inscriptions as votants_count' => fn ($i) => $i->where('a_vote', true),
                'candidates as candidates_actives_count' => fn ($c) => $c->actives(),
            ])
            ->when($this->onglet === 'resultats', fn ($q) => $q->whereIn('statut', [StatutScrutin::Clos->value, StatutScrutin::ResultatsValides->value]))
            ->when($recherche !== '', fn ($q) => $q->where(fn ($r) => $r->whereLike('libelle', "%{$recherche}%")->orWhereLike('code', "%{$recherche}%")))
            ->when($college === Phase::COLLEGE_DGTCP, fn ($q) => $q->whereNull('pole_electoral_id'))
            ->when(CodePoleElectoral::tryFrom($college) !== null, fn ($q) => $q->whereHas('poleElectoral', fn ($p) => $p->where('code', $college)))
            ->when($statut !== null && $this->onglet === 'scrutins', fn ($q) => $q->where('statut', $statut->value))
            ->orderBy('code')
            ->paginate(config('app.lignes_par_page'))
            ->through(fn (Scrutin $scrutin): array => [
                'scrutin' => $scrutin,
                'taux' => $scrutin->inscriptions_count === 0 ? null : round($scrutin->votants_count * 100 / $scrutin->inscriptions_count, 1),
            ]);
    }

    #[Computed]
    public function filtresActifs(): bool
    {
        return trim($this->recherche) !== '' || $this->college !== '' || $this->statut !== '';
    }

    public function paginationView(): string
    {
        return 'pagination.tresor-livewire';
    }

    public function render(): View
    {
        return view('livewire.editions.scrutins-de-la-phase', [
            'colleges' => [
                CodePoleElectoral::Departemental->value => CodePoleElectoral::Departemental->libelle(),
                CodePoleElectoral::Structures->value => CodePoleElectoral::Structures->libelle(),
                Phase::COLLEGE_DGTCP => 'Ensemble de la DGTCP',
            ],
            'collegesDeLaPhase' => $this->phase->colleges()->with('poleElectoral')->get(),
        ]);
    }
}
