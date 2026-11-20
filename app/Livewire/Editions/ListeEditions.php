<?php

namespace App\Livewire\Editions;

use App\Enums\Editions\StatutEdition;
use App\Models\Edition;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Éditions de la Femme modèle du Trésor (module 04), pour le comité, filtrées sans recharger la page.
 */
class ListeEditions extends Component
{
    use WithPagination;

    #[Url]
    public string $recherche = '';

    #[Url]
    public string $statut = '';

    /**
     * Contrôle des droits à chaque requête du composant, mises à jour comprises.
     */
    public function boot(): void
    {
        Gate::authorize('piloter');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Edition>
     */
    #[Computed]
    public function editions(): LengthAwarePaginator
    {
        $recherche = trim($this->recherche);
        $statut = StatutEdition::tryFrom($this->statut);

        return Edition::withCount('scrutins')
            ->when($recherche !== '', fn ($q) => $q->where(fn ($r) => $r
                ->whereLike('libelle', "%{$recherche}%")
                ->when(ctype_digit($recherche), fn ($a) => $a->orWhere('annee', (int) $recherche))))
            ->when($statut !== null, fn ($q) => $q->where('statut', $statut->value))
            ->orderByDesc('annee')
            ->paginate(config('app.lignes_par_page'));
    }

    #[Computed]
    public function filtresActifs(): bool
    {
        return trim($this->recherche) !== '' || $this->statut !== '';
    }

    public function paginationView(): string
    {
        return 'pagination.tresor-livewire';
    }

    public function render(): View
    {
        return view('livewire.editions.liste-editions');
    }
}
