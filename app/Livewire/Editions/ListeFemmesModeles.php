<?php

namespace App\Livewire\Editions;

use App\Models\Edition;
use App\Models\FemmeModele;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Historique des femmes modèles (R59) : recherche, année et pagination sans recharger la page, avec l'inéligibilité
 * de chacune pour l'édition courante.
 */
class ListeFemmesModeles extends Component
{
    use WithPagination;

    #[Url]
    public string $recherche = '';

    #[Url]
    public string $annee = '';

    /**
     * Contrôle des droits à chaque requête du composant, mises à jour comprises.
     */
    public function boot(): void
    {
        Gate::authorize('piloter');
    }

    public function updated(string $propriete): void
    {
        if (in_array($propriete, ['recherche', 'annee'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, FemmeModele>
     */
    #[Computed]
    public function femmesModeles(): LengthAwarePaginator
    {
        $recherche = trim($this->recherche);
        $annee = (int) $this->annee ?: null;

        return FemmeModele::query()
            ->when($recherche !== '', fn ($q) => $q->where(fn ($r) => $r
                ->whereLike('nom_complet', "%{$recherche}%")->orWhereLike('matricule', "%{$recherche}%")))
            ->when($annee !== null, fn ($q) => $q->where('annee', $annee))
            ->orderByDesc('annee')->orderBy('nom_complet')
            ->paginate(config('app.lignes_par_page'));
    }

    /**
     * @return Collection<int, int>
     */
    #[Computed]
    public function annees(): Collection
    {
        return FemmeModele::query()->distinct()->orderByDesc('annee')->pluck('annee');
    }

    public function paginationView(): string
    {
        return 'pagination.tresor-livewire';
    }

    public function render(): View
    {
        $edition = Edition::courante();

        return view('livewire.editions.liste-femmes-modeles', [
            'edition' => $edition,
            'ineligibles' => $edition?->ineligibles() ?? collect(),
            'filtresActifs' => trim($this->recherche) !== '' || $this->annee !== '',
        ]);
    }
}
