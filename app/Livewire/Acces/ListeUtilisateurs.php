<?php

namespace App\Livewire\Acces;

use App\Enums\Acces\CodeRole;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Comptes utilisateurs (module 01), réservés à l'administrateur, filtrés sans recharger la page.
 */
class ListeUtilisateurs extends Component
{
    use WithPagination;

    #[Url]
    public string $recherche = '';

    #[Url]
    public string $role = '';

    /**
     * Contrôle des droits à chaque requête du composant, mises à jour comprises.
     */
    public function boot(): void
    {
        Gate::authorize('administrer');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function utilisateurs(): LengthAwarePaginator
    {
        $recherche = trim($this->recherche);
        $role = CodeRole::tryFrom($this->role);

        return User::query()
            ->with(['roles', 'structure'])
            ->when($recherche !== '', fn ($query) => $query->where(fn ($q) => $q
                ->whereLike('login', "%{$recherche}%")
                ->orWhereLike('name', "%{$recherche}%")))
            ->when($role !== null, fn ($query) => $query->whereRelation('roles', 'code', $role->value))
            ->orderBy('name')
            ->paginate(config('app.lignes_par_page'));
    }

    #[Computed]
    public function filtresActifs(): bool
    {
        return trim($this->recherche) !== '' || $this->role !== '';
    }

    public function paginationView(): string
    {
        return 'pagination.tresor-livewire';
    }

    public function render(): View
    {
        return view('livewire.acces.liste-utilisateurs');
    }
}
