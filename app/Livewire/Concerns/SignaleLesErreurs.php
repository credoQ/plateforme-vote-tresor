<?php

namespace App\Livewire\Concerns;

use Illuminate\Validation\ValidationException;

/**
 * Formulaire Livewire qui appelle une action : les erreurs de champ s'affichent sous leur champ, et une erreur
 * d'action (refus qui ne porte pas sur un champ) s'ouvre aussi dans la fenêtre d'erreur SweetAlert
 * (événement « erreur », écouté par resources/js/alertes.js), sans recharger la page.
 */
trait SignaleLesErreurs
{
    /**
     * Clés d'erreur qui signalent un refus d'action, affiché en fenêtre : les mêmes que celles que le gabarit
     * affiche en fenêtre après une redirection (components/layouts/app.blade.php). Un formulaire qui a un champ du
     * même nom (date_fin d'une édition, par exemple) redéfinit cette liste.
     *
     * @return list<string>
     */
    protected function clesEnFenetre(): array
    {
        return ['edition', 'phase', 'scrutin', 'candidate', 'agent_id', 'date_fin', 'structure', 'role_electeur'];
    }

    protected function signalerErreurs(ValidationException $exception): void
    {
        $erreurs = $exception->validator->errors();
        $this->setErrorBag($erreurs);
        // Formulaire en étapes : rouvre la première étape qui porte une erreur (resources/js/formulaire-etapes.js).
        $this->dispatch('formulaire-erreurs');

        foreach ($this->clesEnFenetre() as $cle) {
            if ($erreurs->has($cle)) {
                $this->dispatch('erreur', message: $erreurs->first($cle));

                return;
            }
        }
    }
}
