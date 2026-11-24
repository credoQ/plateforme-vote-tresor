<?php

namespace App\Livewire\Referentiel;

use App\Models\Departement;
use App\Models\PoleElectoral;
use App\Models\Structure;
use App\Models\TypeStructure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Onglets du référentiel (module 02) : la liste des structures (composant ListeStructures) ou les types de
 * structure et les départements (R5 à R9), filtrés sur place. Changer d'onglet ne recharge pas la page.
 */
class OngletsReferentiel extends Component
{
    /**
     * Onglets de la page.
     */
    public const ONGLETS = ['structures', 'organisation'];

    #[Url]
    public string $onglet = 'structures';

    #[Url(as: 'type_structure')]
    public string $rechercheType = '';

    #[Url]
    public string $pole = '';

    #[Url(as: 'departement_recherche')]
    public string $rechercheDepartement = '';

    /**
     * Contrôle des droits à chaque requête du composant, mises à jour comprises.
     */
    public function boot(): void
    {
        Gate::authorize('viewAny', Structure::class);
    }

    public function mount(): void
    {
        if (! in_array($this->onglet, self::ONGLETS, true)) {
            $this->onglet = 'structures';
        }
    }

    public function choisirOnglet(string $onglet): void
    {
        $this->onglet = in_array($onglet, self::ONGLETS, true) ? $onglet : 'structures';
    }

    /**
     * @return Collection<int, PoleElectoral>
     */
    #[Computed]
    public function poles(): Collection
    {
        return PoleElectoral::with(['typesStructure' => fn ($q) => $q->withCount(['structures' => fn (Builder $s) => $s->where('actif', true)])->orderBy('libelle')])
            ->orderBy('libelle')->get();
    }

    /**
     * Types de structure de chaque pôle, filtrés par libellé ou code et par pôle.
     *
     * @return SupportCollection<int, array{pole: PoleElectoral, type: TypeStructure}>
     */
    #[Computed]
    public function types(): SupportCollection
    {
        return $this->poles
            ->filter(fn (PoleElectoral $pole): bool => $this->pole === '' || $pole->code->value === $this->pole)
            ->flatMap(fn (PoleElectoral $pole) => $pole->typesStructure->map(fn (TypeStructure $type): array => ['pole' => $pole, 'type' => $type]))
            ->filter(fn (array $ligne): bool => $this->contient($ligne['type']->code->libelle().' '.$ligne['type']->code->value, $this->rechercheType))
            ->values();
    }

    /**
     * @return SupportCollection<int, Departement>
     */
    #[Computed]
    public function departements(): SupportCollection
    {
        return Departement::query()
            ->with('tresorerieDepartementale')
            ->withCount('tresoreriesCommunales')
            ->orderBy('libelle')->get()
            ->filter(fn (Departement $departement): bool => $this->contient($departement->code.' '.$departement->libelle, $this->rechercheDepartement))
            ->values();
    }

    public function render(): View
    {
        return view('livewire.referentiel.onglets-referentiel');
    }

    private function contient(string $texte, string $terme): bool
    {
        $terme = trim($terme);

        return $terme === '' || str_contains(mb_strtolower($texte), mb_strtolower($terme));
    }
}
