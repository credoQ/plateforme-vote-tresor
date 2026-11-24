<?php

namespace App\Livewire\Agents;

use App\Models\Affectation;
use App\Models\Agent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Historique des affectations sur la fiche d'un agent (R12) : filtré et paginé sans recharger la page.
 * Le point focal n'y accède que pour un agent de son périmètre (R4).
 */
class HistoriqueAffectations extends Component
{
    use WithPagination;

    public Agent $agent;

    #[Url]
    public string $recherche = '';

    #[Url]
    public string $periode = '';

    public function mount(): void
    {
        Gate::authorize('view', $this->agent);
    }

    /**
     * Contrôle des droits à chaque mise à jour du composant.
     */
    public function hydrate(): void
    {
        Gate::authorize('view', $this->agent);
    }

    public function updated(string $propriete): void
    {
        if (in_array($propriete, ['recherche', 'periode'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, Affectation>
     */
    #[Computed]
    public function affectations(): LengthAwarePaginator
    {
        $recherche = trim($this->recherche);

        return $this->agent->affectations()
            ->with('structure')
            ->when($recherche !== '', fn ($q) => $q->whereHas('structure', fn ($s) => $s
                ->whereLike('code', "%{$recherche}%")->orWhereLike('libelle', "%{$recherche}%")))
            ->when($this->periode === 'en-cours', fn ($q) => $q->whereNull('date_fin'))
            ->when($this->periode === 'terminees', fn ($q) => $q->whereNotNull('date_fin'))
            ->paginate(config('app.lignes_par_page'));
    }

    #[Computed]
    public function filtresActifs(): bool
    {
        return trim($this->recherche) !== '' || $this->periode !== '';
    }

    public function paginationView(): string
    {
        return 'pagination.tresor-livewire';
    }

    public function render(): View
    {
        return view('livewire.agents.historique-affectations', [
            // Les filtres ne s'affichent que s'il y a de quoi filtrer.
            'avecFiltres' => $this->filtresActifs || $this->agent->affectations()->count() > 5,
        ]);
    }
}
