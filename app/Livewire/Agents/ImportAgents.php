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
 * Import des agents par fichier (administrateur) : dépôt d'un classeur Excel ou d'un CSV, import en file d'attente
 * (agents, comptes électeurs, affectations), puis bilan et lignes rejetées.
 */
class ImportAgents extends Component
{
    use SignaleLesErreurs, WithFileUploads;

    public ?TemporaryUploadedFile $fichier = null;

    public string $dateEffet = '';

    public function boot(): void
    {
        Gate::authorize('create', Agent::class);
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
        $this->reset('fichier');
        unset($this->import);

        $this->dispatch('notifier', message: 'Import lancé : le bilan s’affiche ici dès qu’il est terminé.', ton: 'succes');
    }

    public function fermerBilan(SuivreImportAgents $suivi): void
    {
        $suivi->oublier((int) Auth::id());
        unset($this->import);
    }

    public function render(): View
    {
        return view('livewire.agents.import-agents');
    }
}
