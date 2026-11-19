<?php

namespace App\View\Composers;

use App\Models\Edition;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Données du gabarit de l'espace électeur (components.layouts.electeur) : nom, matricule et initiales de l'utilisateur,
 * année de l'édition en cours, et lien vers la console de gestion pour un profil de gestion qui vote aussi.
 */
class EspaceElecteurComposer
{
    public function compose(View $view): void
    {
        /** @var User $utilisateur */
        $utilisateur = Auth::user();

        $view->with([
            'utilisateur' => $utilisateur,
            'initiales' => $utilisateur->initiales(),
            'editionEnCours' => Edition::enCours()->value('annee'),
            'consoleDeGestion' => $utilisateur->aAccesALaGestion() ? route('tableau-de-bord') : null,
        ]);
    }
}
