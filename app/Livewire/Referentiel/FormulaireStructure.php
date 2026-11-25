<?php

namespace App\Livewire\Referentiel;

use App\Actions\Audit\Journaliser;
use App\Actions\Referentiel\EnregistrerStructure;
use App\Enums\Audit\ActionAuditee;
use App\Enums\Referentiel\CodeTypeStructure;
use App\Livewire\Concerns\SignaleLesErreurs;
use App\Models\Departement;
use App\Models\Structure;
use App\Models\TypeStructure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Création et modification d'une structure du référentiel (R5 à R10), en trois étapes, envoyées sans recharger
 * la page. La validation reste dans l'action EnregistrerStructure ; l'enregistrement est tracé au journal (R56).
 */
class FormulaireStructure extends Component
{
    use SignaleLesErreurs;

    public ?Structure $structure = null;

    public string $code = '';

    public string $sigle = '';

    public string $libelle = '';

    public string $niveau = '';

    public string $typeStructureId = '';

    public string $departementId = '';

    public string $structureParenteId = '';

    /**
     * Nouvelle structure, éventuellement un service à rattacher à la structure $parente (bouton « Ajouter un service »).
     */
    public function mount(?Structure $structure = null, ?int $parente = null): void
    {
        $this->structure = $structure?->exists ? $structure : null;
        $this->autoriser();

        $this->niveau = (string) Structure::NIVEAU_STRUCTURE_ELECTORALE;
        if ($this->structure !== null) {
            $this->code = $this->structure->code;
            $this->sigle = (string) $this->structure->sigle;
            $this->libelle = $this->structure->libelle;
            $this->niveau = (string) $this->structure->niveau;
            $this->typeStructureId = (string) $this->structure->type_structure_id;
            $this->departementId = (string) $this->structure->departement_id;
            $this->structureParenteId = (string) $this->structure->structure_parente_id;
        } elseif ($parente !== null && ($mere = Structure::find($parente)) !== null) {
            $this->niveau = (string) Structure::NIVEAU_SERVICE;
            $this->typeStructureId = (string) $mere->type_structure_id;
            $this->departementId = (string) $mere->departement_id;
            $this->structureParenteId = (string) $mere->id;
        }
    }

    /**
     * Contrôle des droits à chaque mise à jour du composant, envoi compris.
     */
    public function hydrate(): void
    {
        $this->autoriser();
    }

    /**
     * @return Collection<int, TypeStructure>
     */
    #[Computed]
    public function types(): Collection
    {
        return TypeStructure::with('poleElectoral')->orderBy('libelle')->get();
    }

    /**
     * Structures de rattachement possibles : les structures électorales actives (ou la mère actuelle), sauf elle-même.
     *
     * @return Collection<int, Structure>
     */
    #[Computed]
    public function parentes(): Collection
    {
        return Structure::query()
            ->where('niveau', Structure::NIVEAU_STRUCTURE_ELECTORALE)
            ->where(fn (Builder $q) => $q->where('actif', true)->orWhere('id', $this->structure?->structure_parente_id))
            ->when($this->structure !== null, fn (Builder $q) => $q->whereKeyNot($this->structure->id))
            ->orderBy('code')
            ->get(['id', 'code', 'libelle']);
    }

    public function enregistrer(EnregistrerStructure $enregistrer, Journaliser $journal): mixed
    {
        $this->resetErrorBag();
        $creation = $this->structure === null;
        $entier = fn (string $valeur): ?int => $valeur !== '' ? (int) $valeur : null;

        try {
            $structure = DB::transaction(function () use ($enregistrer, $journal, $creation, $entier): Structure {
                $structure = $enregistrer->handle([
                    'code' => mb_strtoupper(trim($this->code)),
                    'sigle' => trim($this->sigle) !== '' ? trim($this->sigle) : null,
                    'libelle' => trim($this->libelle),
                    'niveau' => $entier($this->niveau) ?? Structure::NIVEAU_STRUCTURE_ELECTORALE,
                    'type_structure_id' => $entier($this->typeStructureId),
                    'departement_id' => $entier($this->departementId),
                    // La structure de rattachement ne vaut que pour un service ou une Trésorerie communale (R8).
                    'structure_parente_id' => $this->avecRattachement() ? $entier($this->structureParenteId) : null,
                ], $this->structure);
                $journal->handle(ActionAuditee::StructureEnregistree, $structure, $creation
                    ? ['code' => $structure->code, 'creation' => true]
                    : ['code' => $structure->code, 'creation' => false, 'modifications' => array_keys($structure->getChanges())]);

                return $structure;
            });
        } catch (ValidationException $exception) {
            $this->signalerErreurs($exception);

            return null;
        }

        session()->flash('succes', $creation ? "La structure {$structure->code} est enregistrée." : 'La structure est enregistrée.');

        return $this->redirectRoute('structures.show', $structure, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.referentiel.formulaire-structure', [
            'departements' => Departement::orderBy('libelle')->get(),
            'codeType' => $this->typeChoisi()?->code->value ?? '',
            'avecRattachement' => $this->avecRattachement(),
            'codeTypeTresorerieCommunale' => CodeTypeStructure::TresorerieCommunale->value,
        ]);
    }

    private function typeChoisi(): ?TypeStructure
    {
        return $this->types->firstWhere('id', (int) $this->typeStructureId);
    }

    private function avecRattachement(): bool
    {
        return $this->niveau === (string) Structure::NIVEAU_SERVICE
            || $this->typeChoisi()?->code === CodeTypeStructure::TresorerieCommunale;
    }

    private function autoriser(): void
    {
        $this->structure === null ? Gate::authorize('create', Structure::class) : Gate::authorize('update', $this->structure);
    }
}
