<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Espace électeur. Son contenu (scrutin de la phase ouverte, candidatures, qualification, résultats publiés) est
 * dans le composant Livewire Electeur\Accueil. Un compte de gestion sans fiche agent est renvoyé vers la console.
 */
class EspaceController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $utilisateur = $request->user();

        // Un compte de gestion sans fiche agent (administrateur technique, observateur externe) ne vote pas.
        if ($utilisateur->agent_id === null && $utilisateur->aAccesALaGestion()) {
            return redirect()->route('tableau-de-bord');
        }

        return view('espace.accueil');
    }
}
