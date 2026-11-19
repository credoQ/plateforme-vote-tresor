<?php

namespace App\Livewire\Agents;

use App\Enums\Acces\CodeRole;
use App\Enums\Agents\Sexe;
use App\Models\Agent;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Liste des agents (module 03), filtrée sans recharger la page. Le point focal ne voit que les agents
 * affectés dans son périmètre (R4). Les filtres sont gardés dans l'adresse de la page.
 */
class ListeAgents extends Component
{
    use WithPagination;

    private const PAR_PAGE = 50;

    #[Url]
    public string $recherche = '';

    #[Url]
    public string $structure = '';

    #[Url]
    public string $sexe = '';

    #[Url]
    public string $situation = 'en-service';

    /**
     * Contrôle des droits à chaque requête du composant, mises à jour comprises (pas seulement à l'affichage).
     */
    public function boot(): void
    {
        Gate::authorize('viewAny', Agent::class);
    }

    public function updated(string $propriete): void
    {
        if (in_array($propriete, ['recherche', 'structure', 'sexe', 'situation'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Structures du point focal, ou null pour les profils qui voient toute la DGTCP.
     *
     * @return list<int>|null
     */
    #[Computed]
    public function perimetre(): ?array
    {
        /** @var User $utilisateur */
        $utilisateur = Auth::user();

        return $utilisateur->aLeRole(CodeRole::Administrateur, CodeRole::Comite, CodeRole::Observateur) ? null : $utilisateur->perimetre();
    }

    /**
     * @return LengthAwarePaginator<int, Agent>
     */
    #[Computed]
    public function agents(): LengthAwarePaginator
    {
        $recherche = trim($this->recherche);
        $structureId = (int) $this->structure ?: null;
        $sexe = Sexe::tryFrom($this->sexe);

        return Agent::query()
            ->with('affectationEnCours.structure')
            ->when($this->perimetre !== null, fn ($q) => $q->affectesLe(Carbon::today(), $this->perimetre))
            ->when($structureId !== null, fn ($q) => $q->affectesLe(Carbon::today(), [$structureId]))
            ->when($recherche !== '', fn ($q) => $q->where(fn ($r) => $r
                ->whereLike('matricule', "%{$recherche}%")->orWhereLike('nom', "%{$recherche}%")
                ->orWhereLike('prenoms', "%{$recherche}%")->orWhereLike('npi', "%{$recherche}%")))
            ->when($sexe !== null, fn ($q) => $q->where('sexe', $sexe->value))
            ->when($this->situation === 'en-service', fn ($q) => $q->enServiceLe())
            ->when($this->situation === 'hors-service', fn ($q) => $q->whereDoesntHave('affectations', fn ($a) => $a->enCoursLe()))
            ->orderBy('nom')->orderBy('prenoms')
            ->paginate(self::PAR_PAGE);
    }

    /**
     * @return Collection<int, Structure>
     */
    #[Computed]
    public function structures(): Collection
    {
        return Structure::actives()
            ->when($this->perimetre !== null, fn ($q) => $q->whereKey($this->perimetre))
            ->orderBy('code')
            ->get(['id', 'code', 'libelle']);
    }

    #[Computed]
    public function filtresActifs(): bool
    {
        return trim($this->recherche) !== '' || $this->structure !== '' || $this->sexe !== '' || $this->situation !== 'en-service';
    }

    public function paginationView(): string
    {
        return 'pagination.tresor-livewire';
    }

    public function render(): View
    {
        return view('livewire.agents.liste-agents', ['restreint' => $this->perimetre !== null]);
    }
}
