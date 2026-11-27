<?php

namespace App\Livewire\Agents;

use App\Actions\Agents\SuivreImportAgents;
use App\Livewire\Concerns\SignaleLesErreurs;
use App\Models\Agent;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Fenêtre d'import des agents par fichier (administrateur), ouverte depuis la liste des agents : dépôt d'un classeur
 * Excel ou d'un CSV, import en file d'attente (agents, comptes électeurs, affectations), progression, puis bilan et
 * lignes rejetées. La liste des agents se rafraîchit à la fin de l'import.
 */
class ImportAgents extends Component
{
    use SignaleLesErreurs, WithFileUploads;

    public bool $ouvert = false;

    public ?TemporaryUploadedFile $fichier = null;

    public string $dateEffet = '';

    /**
     * État vu à la requête précédente : la fin d'un import (en cours → terminé) rafraîchit la liste des agents.
     */
    public ?string $etatConnu = null;

    public function boot(): void
    {
        Gate::authorize('create', Agent::class);
    }

    public function mount(): void
    {
        $this->etatConnu = $this->import['etat'] ?? null;
        // Un import encore en cours rouvre la fenêtre pour suivre sa progression.
        $this->ouvert = $this->etatConnu === SuivreImportAgents::EN_COURS;
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function import(): ?array
    {
        return app(SuivreImportAgents::class)->etat((int) Auth::id());
    }

    public function importer(SuivreImportAgents $suivi): void
    {
        try {
            $this->validate([
                'fichier' => ['required', 'file', 'max:10240', 'extensions:xlsx,csv,txt'],
                'dateEffet' => ['nullable', 'date_format:Y-m-d'],
            ], [
                'fichier.required' => 'Choisissez le fichier des agents.',
                'fichier.max' => 'Le fichier dépasse 10 Mo.',
                'fichier.extensions' => 'Le fichier doit être un classeur Excel (.xlsx) ou un fichier CSV.',
                'dateEffet.date_format' => 'Date invalide.',
            ]);
        } catch (ValidationException $erreur) {
            $this->signalerErreurs($erreur);

            return;
        }

        if (($this->import['etat'] ?? null) === SuivreImportAgents::EN_COURS) {
            $this->dispatch('erreur', message: 'Un import est déjà en cours : attendez qu’il se termine.');

            return;
        }

        $suivi->lancer(Auth::user(), $this->fichier, $this->dateEffet !== '' ? $this->dateEffet : null);
        $this->reset('fichier', 'dateEffet');
        $this->etatConnu = SuivreImportAgents::EN_COURS;
        $this->suivre();
    }

    /**
     * Relu toutes les trois secondes pendant l'import : à la fin, la liste des agents se rafraîchit.
     */
    public function suivre(): void
    {
        unset($this->import);
        $etat = $this->import['etat'] ?? null;

        if ($this->etatConnu === SuivreImportAgents::EN_COURS && $etat === SuivreImportAgents::TERMINE) {
            $this->dispatch('agents-importes');
        }
        $this->etatConnu = $etat;
    }

    /**
     * Efface le bilan pour déposer un autre fichier.
     */
    public function nouvelImport(SuivreImportAgents $suivi): void
    {
        $suivi->oublier((int) Auth::id());
        $this->etatConnu = null;
        unset($this->import);
    }

    public function render(): View
    {
        return view('livewire.agents.import-agents');
    }
}
