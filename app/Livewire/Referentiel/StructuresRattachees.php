<?php

namespace App\Livewire\Referentiel;

use App\Models\Structure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Structures rattachées à une structure (R8), sur sa fiche : recherche, statut et pagination sans recharger la page.
 */
class StructuresRattachees extends Component
{
    use WithPagination;

    public Structure $structure;

    #[Url]
    public string $recherche = '';

    #[Url]
    public string $statut = 'toutes';

    public function mount(): void
    {
        Gate::authorize('view', $this->structure);
    }

    /**
     * Contrôle des droits à chaque mise à jour du composant.
     */
    public function hydrate(): void
    {
        Gate::authorize('view', $this->structure);
    }

    public function updated(string $propriete): void
    {
        if (in_array($propriete, ['recherche', 'statut'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, Structure>
     */
    #[Computed]
    public function structuresFilles(): LengthAwarePaginator
    {
        $recherche = trim($this->recherche);

        return $this->structure->structuresFilles()
            ->with('typeStructure')
            ->withCount(['affectations as agents_en_service_count' => fn (Builder $q) => $q->enCoursLe()])
            ->when($recherche !== '', fn ($q) => $q->where(fn ($r) => $r
                ->whereLike('code', "%{$recherche}%")->orWhereLike('sigle', "%{$recherche}%")->orWhereLike('libelle', "%{$recherche}%")))
            ->when($this->statut === 'actives', fn ($q) => $q->where('actif', true))
            ->when($this->statut === 'desactivees', fn ($q) => $q->where('actif', false))
            ->orderBy('code')
            ->paginate(config('app.lignes_par_page'));
    }

    #[Computed]
    public function filtresActifs(): bool
    {
        return trim($this->recherche) !== '' || $this->statut !== 'toutes';
    }

    public function paginationView(): string
    {
        return 'pagination.tresor-livewire';
    }

    public function render(): View
    {
        return view('livewire.referentiel.structures-rattachees');
    }
}
