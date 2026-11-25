<?php

namespace App\Http\Controllers\Editions;

use App\Actions\Editions\SupprimerFemmeModele;
use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\FemmeModele;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Historique des femmes modèles qui fonde l'inéligibilité (R59) : consultation, ajout par le comité des femmes
 * modèles des éditions antérieures, retrait d'une ligne saisie par erreur.
 */
class FemmeModeleController extends Controller
{
    /**
     * Historique des femmes modèles : la liste filtrée est dans le composant Livewire Editions\ListeFemmesModeles.
     */
    public function index(): View
    {
        $edition = Edition::courante();

        return view('femmes-modeles.index', ['edition' => $edition]);
    }

    /**
     * Page d'ajout : le formulaire est le composant Livewire Editions\FormulaireFemmeModele.
     */
    public function create(): View
    {
        return view('femmes-modeles.formulaire');
    }

    public function destroy(FemmeModele $femmeModele, SupprimerFemmeModele $supprimer): RedirectResponse
    {
        $supprimer->handle($femmeModele);

        return redirect()->route('femmes-modeles.index')->with('succes', "{$femmeModele->nom_complet} ({$femmeModele->annee}) est retirée de l’historique.");
    }
}
