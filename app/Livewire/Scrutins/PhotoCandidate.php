<?php

namespace App\Livewire\Scrutins;

use App\Actions\Scrutins\ChangerPhotoCandidate;
use App\Livewire\Concerns\SignaleLesErreurs;
use App\Models\Candidate;
use App\Models\Inscription;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Fenêtre d'ajout, de remplacement ou de retrait de la photo d'une candidate, ouverte par l'événement
 * « ouvrir-photo-candidate » (onglet Candidates d'un scrutin, espace de la candidate). La photo est recadrée dans
 * le navigateur (resources/js/recadrage-photo.js) au format de la carte du bulletin, puis envoyée.
 */
class PhotoCandidate extends Component
{
    use SignaleLesErreurs, WithFileUploads;

    public ?int $candidateId = null;

    public ?TemporaryUploadedFile $photo = null;

    #[On('ouvrir-photo-candidate')]
    public function ouvrir(int $candidate): void
    {
        Gate::authorize('changerPhoto', Candidate::findOrFail($candidate));

        $this->resetErrorBag();
        $this->photo = null;
        $this->candidateId = $candidate;
        unset($this->candidate);
        $this->dispatch('photo-candidate-ouverte');
    }

    /**
     * @return array{candidate: Candidate, structure: string, initiales: string}|null
     */
    #[Computed]
    public function candidate(): ?array
    {
        $candidate = $this->candidateId !== null ? Candidate::with('agent')->find($this->candidateId) : null;
        if ($candidate === null) {
            return null;
        }

        $structure = Inscription::where('scrutin_id', $candidate->scrutin_id)->where('agent_id', $candidate->agent_id)->with('structure:id,libelle')->first()?->structure
            ?? $candidate->agent->affectationLe()?->structure;

        return [
            'candidate' => $candidate,
            'structure' => $structure?->libelle ?? '',
            'initiales' => $candidate->agent->initiales(),
        ];
    }

    public function enregistrer(ChangerPhotoCandidate $changer): void
    {
        $this->changer($changer, $this->photo, 'La photo est enregistrée : elle apparaît sur le bulletin.');
    }

    public function retirer(ChangerPhotoCandidate $changer): void
    {
        $this->changer($changer, null, 'La photo est retirée : les initiales apparaissent sur le bulletin.');
    }

    public function render(): View
    {
        return view('livewire.scrutins.photo-candidate', [
            'tailleMaximale' => ChangerPhotoCandidate::TAILLE_MAXIMALE,
        ]);
    }

    private function changer(ChangerPhotoCandidate $changer, ?TemporaryUploadedFile $photo, string $succes): void
    {
        $candidate = Candidate::findOrFail($this->candidateId);
        Gate::authorize('changerPhoto', $candidate);
        $this->resetErrorBag();

        try {
            $changer->handle($candidate, $photo, Auth::user());
        } catch (ValidationException $exception) {
            $this->signalerErreurs($exception);

            return;
        }

        $this->photo = null;
        $this->candidateId = null;
        $this->dispatch('photo-candidate-modifiee');
        $this->dispatch('notifier', message: $succes, ton: 'succes');
    }
}
