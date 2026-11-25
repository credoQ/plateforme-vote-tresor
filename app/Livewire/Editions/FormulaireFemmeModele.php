<?php

namespace App\Livewire\Editions;

use App\Actions\Editions\AjouterFemmeModele;
use App\Livewire\Concerns\SignaleLesErreurs;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Ajout d'une femme modèle d'une édition antérieure à l'historique (R59), envoyé sans recharger la page.
 * La validation reste dans l'action AjouterFemmeModele.
 */
class FormulaireFemmeModele extends Component
{
    use SignaleLesErreurs;

    public string $matricule = '';

    public string $annee = '';

    public string $nom = '';

    public string $scrutin = '';

    /**
     * Contrôle des droits à chaque requête du composant, envoi compris.
     */
    public function boot(): void
    {
        Gate::authorize('piloter');
    }

    public function enregistrer(AjouterFemmeModele $ajouter): mixed
    {
        $this->resetErrorBag();

        try {
            $femmeModele = $ajouter->handle([
                'matricule' => $this->matricule,
                'annee' => $this->annee,
                'nom' => $this->nom,
                'scrutin' => $this->scrutin,
            ]);
        } catch (ValidationException $exception) {
            $this->signalerErreurs($exception);

            return null;
        }

        session()->flash('succes', "{$femmeModele->nom_complet} ({$femmeModele->annee}) est ajoutée à l’historique.");

        return $this->redirectRoute('femmes-modeles.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.editions.formulaire-femme-modele', [
            'anneeMaximale' => now()->year,
            'anneeMinimale' => AjouterFemmeModele::ANNEE_MINIMALE,
        ]);
    }
}
