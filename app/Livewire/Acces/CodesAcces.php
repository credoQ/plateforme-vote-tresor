<?php

namespace App\Livewire\Acces;

use App\Actions\Acces\GenererCodesAcces;
use App\Enums\Acces\ModeConnexion;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Codes d'accès du mode « code imprimé » (comité et administrateur) : comptes avec ou sans code, génération des codes
 * manquants, régénération d'un code perdu, coupons à imprimer. Les codes ne s'affichent jamais à l'écran.
 */
class CodesAcces extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(except: '')]
    public string $etat = '';

    /**
     * Contrôle des droits à chaque requête du composant.
     */
    public function boot(): void
    {
        Gate::authorize('gerer-codes-acces');
    }

    public function updated(string $propriete): void
    {
        if (in_array($propriete, ['recherche', 'etat'], true)) {
            $this->resetPage();
        }
    }

    public function paginationView(): string
    {
        return 'pagination.tresor-livewire';
    }

    /**
     * @return array{comptes: int, avecCode: int, sansCode: int}
     */
    #[Computed]
    public function bilan(): array
    {
        $comptes = User::where('actif', true)->count();
        $avecCode = User::where('actif', true)->whereNotNull('code_acces')->count();

        return ['comptes' => $comptes, 'avecCode' => $avecCode, 'sansCode' => $comptes - $avecCode];
    }

    #[Computed]
    public function comptes(): LengthAwarePaginator
    {
        $terme = trim($this->recherche);

        return User::query()
            ->where('actif', true)
            ->with('roles')
            ->when($terme !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q->whereLike('name', "%{$terme}%")->orWhereLike('login', "%{$terme}%")))
            ->when($this->etat === 'avec', fn (Builder $q) => $q->whereNotNull('code_acces'))
            ->when($this->etat === 'sans', fn (Builder $q) => $q->whereNull('code_acces'))
            ->orderBy('name')
            ->paginate(20, ['id', 'uuid', 'name', 'login', 'code_acces_genere_le']);
    }

    public function genererManquants(GenererCodesAcces $generer): void
    {
        $nombre = $generer->handle(Auth::user());
        unset($this->bilan, $this->comptes);

        $this->dispatch('notifier', message: $nombre === 0 ? 'Tous les comptes actifs ont déjà un code.' : "{$nombre} code(s) généré(s). Imprimez les coupons pour les remettre.", ton: 'succes');
    }

    public function regenerer(int $compte, GenererCodesAcces $generer): void
    {
        $compte = $generer->regenerer(User::findOrFail($compte), Auth::user());
        unset($this->bilan, $this->comptes);

        $this->dispatch('notifier', message: "Nouveau code pour {$compte->name} : l’ancien ne fonctionne plus. Imprimez son coupon.", ton: 'succes');
    }

    public function render(): View
    {
        return view('livewire.acces.codes-acces', ['codeImprime' => ModeConnexion::codeImprime()]);
    }
}
