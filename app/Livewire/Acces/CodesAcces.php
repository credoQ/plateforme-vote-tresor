<?php

namespace App\Livewire\Acces;

use App\Actions\Acces\GenererCodesAcces;
use App\Actions\Acces\ImprimerCodesAcces;
use App\Enums\Acces\ModeConnexion;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Codes d'accès du mode « code imprimé » (comité et administrateur) : comptes avec ou sans code, par structure
 * d'affectation ; génération des codes manquants et impression des coupons, pour tous ou pour une sélection ;
 * régénération d'un code perdu. Les codes ne s'affichent jamais à l'écran.
 */
class CodesAcces extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(except: '')]
    public string $etat = '';

    #[Url(except: '')]
    public string $structure = '';

    /**
     * Comptes cochés (identifiants), conservés d'une page à l'autre.
     *
     * @var list<string>
     */
    public array $selection = [];

    /**
     * Contrôle des droits à chaque requête du composant.
     */
    public function boot(): void
    {
        Gate::authorize('gerer-codes-acces');
    }

    public function updated(string $propriete): void
    {
        if (in_array($propriete, ['recherche', 'etat', 'structure'], true)) {
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

    /**
     * @return Collection<int, Structure>
     */
    #[Computed]
    public function structures(): Collection
    {
        return Structure::actives()->orderBy('code')->get(['id', 'code', 'libelle']);
    }

    #[Computed]
    public function comptes(): LengthAwarePaginator
    {
        return $this->requete()->with('roles')->orderBy('name')
            ->paginate(20, ['id', 'uuid', 'name', 'login', 'code_acces_genere_le']);
    }

    /**
     * Coche ou décoche tous les comptes de la page affichée.
     */
    public function basculerPage(): void
    {
        $page = $this->comptes->getCollection()->map(fn (User $compte): string => (string) $compte->id)->all();
        $toutesCochees = array_diff($page, $this->selection) === [];

        $this->selection = $toutesCochees
            ? array_values(array_diff($this->selection, $page))
            : array_values(array_unique([...$this->selection, ...$page]));
    }

    /**
     * Coche tous les comptes qui répondent aux filtres, toutes pages confondues.
     */
    public function selectionnerTout(): void
    {
        $this->selection = $this->requete()->pluck('id')->map(fn (int $id): string => (string) $id)->all();
    }

    public function viderSelection(): void
    {
        $this->selection = [];
    }

    public function genererManquants(GenererCodesAcces $generer): void
    {
        $nombre = $generer->handle(Auth::user());
        $this->apresGeneration($nombre);
    }

    public function genererSelection(GenererCodesAcces $generer): void
    {
        $nombre = $generer->handle(Auth::user(), $this->identifiants());
        $this->apresGeneration($nombre);
    }

    /**
     * Coupons des comptes cochés qui ont un code, en un PDF par structure.
     */
    public function imprimerSelection(ImprimerCodesAcces $imprimer): ?StreamedResponse
    {
        $document = $imprimer->selection($this->identifiants(), Auth::user());
        if ($document['nombre'] === 0) {
            $this->dispatch('erreur', message: 'Aucun des comptes cochés n’a encore de code : générez d’abord leurs codes.');

            return null;
        }

        return response()->streamDownload(function () use ($document): void {
            echo $document['pdf'];
        }, $document['nom'], ['Content-Type' => 'application/pdf']);
    }

    public function regenerer(int $compte, GenererCodesAcces $generer): void
    {
        $compte = $generer->regenerer(User::findOrFail($compte), Auth::user());
        unset($this->bilan, $this->comptes);

        $this->dispatch('notifier', message: "Nouveau code pour {$compte->name} : l’ancien ne fonctionne plus. Imprimez son coupon.", ton: 'succes');
    }

    public function render(): View
    {
        return view('livewire.acces.codes-acces', [
            'codeImprime' => ModeConnexion::codeImprime(),
            'pageCochee' => $this->comptes->isNotEmpty() && array_diff($this->comptes->getCollection()->map(fn (User $compte): string => (string) $compte->id)->all(), $this->selection) === [],
            'filtresActifs' => trim($this->recherche) !== '' || $this->etat !== '' || $this->structure !== '',
        ]);
    }

    /**
     * Comptes actifs qui répondent aux filtres (nom ou matricule, code, structure d'affectation en cours).
     *
     * @return Builder<User>
     */
    private function requete(): Builder
    {
        $terme = trim($this->recherche);

        return User::query()
            ->where('actif', true)
            ->when($terme !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q->whereLike('name', "%{$terme}%")->orWhereLike('login', "%{$terme}%")))
            ->when($this->etat === 'avec', fn (Builder $q) => $q->whereNotNull('code_acces'))
            ->when($this->etat === 'sans', fn (Builder $q) => $q->whereNull('code_acces'))
            ->when($this->structure !== '', fn (Builder $q) => $q->whereHas('agent.affectations', fn (Builder $a) => $a->enCoursLe()->where('structure_id', (int) $this->structure)));
    }

    /**
     * @return list<int>
     */
    private function identifiants(): array
    {
        return array_map('intval', $this->selection);
    }

    private function apresGeneration(int $nombre): void
    {
        unset($this->bilan, $this->comptes);

        $this->dispatch('notifier', message: $nombre === 0 ? 'Ces comptes ont déjà un code.' : "{$nombre} code(s) généré(s). Imprimez les coupons pour les remettre.", ton: 'succes');
    }
}
