<?php

namespace App\Livewire\Referentiel;

use App\Models\Departement;
use App\Models\Structure;
use App\Models\TypeStructure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Référentiel des structures (module 02), filtré sans recharger la page ; les filtres restent dans l'adresse.
 */
class ListeStructures extends Component
{
    use WithPagination;

    #[Url]
    public string $recherche = '';

    #[Url]
    public string $type = '';

    #[Url]
    public string $departement = '';

    #[Url]
    public string $statut = 'actives';

    /**
     * Contrôle des droits à chaque requête du composant, mises à jour comprises.
     */
    public function boot(): void
    {
        Gate::authorize('viewAny', Structure::class);
    }

    public function updated(string $propriete): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Structure>
     */
    #[Computed]
    public function structures(): LengthAwarePaginator
    {
        $recherche = trim($this->recherche);

        return Structure::query()
            ->with(['typeStructure', 'departement', 'structureParente'])
            ->withCount(['affectations as agents_en_service_count' => fn (Builder $q) => $q->enCoursLe()])
            ->when($recherche !== '', fn (Builder $q) => $q->where(fn (Builder $r) => $r
                ->whereLike('code', "%{$recherche}%")->orWhereLike('sigle', "%{$recherche}%")->orWhereLike('libelle', "%{$recherche}%")))
            ->when((int) $this->type > 0, fn (Builder $q) => $q->where('type_structure_id', (int) $this->type))
            ->when((int) $this->departement > 0, fn (Builder $q) => $q->where('departement_id', (int) $this->departement))
            ->when($this->statut === 'actives', fn (Builder $q) => $q->where('actif', true))
            ->when($this->statut === 'desactivees', fn (Builder $q) => $q->where('actif', false))
            ->orderBy('code')
            ->paginate(config('app.lignes_par_page'));
    }

    /**
     * @return Collection<int, TypeStructure>
     */
    #[Computed(persist: true)]
    public function types(): Collection
    {
        return TypeStructure::orderBy('libelle')->get();
    }

    /**
     * @return Collection<int, Departement>
     */
    #[Computed(persist: true)]
    public function departements(): Collection
    {
        return Departement::orderBy('libelle')->get();
    }

    #[Computed]
    public function filtresActifs(): bool
    {
        return trim($this->recherche) !== '' || $this->type !== '' || $this->departement !== '' || $this->statut !== 'actives';
    }

    public function paginationView(): string
    {
        return 'pagination.tresor-livewire';
    }

    public function render(): View
    {
        return view('livewire.referentiel.liste-structures');
    }
}
