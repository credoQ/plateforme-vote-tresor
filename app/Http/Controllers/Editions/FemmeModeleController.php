<?php

namespace App\Http\Controllers\Editions;

use App\Actions\Editions\ImporterFemmesModeles;
use App\Actions\Editions\SupprimerFemmeModele;
use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\FemmeModele;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Historique des femmes modèles qui fonde l'inéligibilité (R59) : consultation, import des éditions antérieures,
 * suppression d'une ligne importée par erreur (comité).
 */
class FemmeModeleController extends Controller
{
    public function index(): View
    {
        $edition = Edition::courante();
        $ineligibles = $edition?->ineligibles() ?? collect();

        return view('femmes-modeles.index', [
            'femmesModeles' => FemmeModele::orderByDesc('annee')->orderBy('nom_complet')->get(),
            'edition' => $edition,
            'ineligibles' => $ineligibles,
        ]);
    }

    public function importer(Request $request, ImporterFemmesModeles $importer): RedirectResponse
    {
        $request->validate(['fichier' => ['required', 'file', 'max:2048', 'extensions:csv,txt,xlsx']], [
            'fichier.required' => 'Choisissez le fichier à importer.',
            'fichier.extensions' => 'Le fichier est un classeur Excel (.xlsx) ou un fichier CSV.',
            'fichier.max' => 'Le fichier ne dépasse pas 2 Mo.',
        ]);

        $fichier = $request->file('fichier');
        $chemin = $fichier->getRealPath().'.'.mb_strtolower($fichier->getClientOriginalExtension());
        copy($fichier->getRealPath(), $chemin);

        try {
            $rapport = $importer->handle($chemin);
        } catch (InvalidArgumentException $exception) {
            return redirect()->route('femmes-modeles.index')->withErrors(['fichier' => $exception->getMessage()]);
        } finally {
            @unlink($chemin);
        }

        return redirect()->route('femmes-modeles.index')
            ->with('succes', "{$rapport->enregistrees} femme(s) modèle(s) enregistrée(s).")
            ->with('rejets', $rapport->rejets);
    }

    public function destroy(FemmeModele $femmeModele, SupprimerFemmeModele $supprimer): RedirectResponse
    {
        $supprimer->handle($femmeModele);

        return redirect()->route('femmes-modeles.index')->with('succes', "{$femmeModele->nom_complet} ({$femmeModele->annee}) est retirée de l’historique.");
    }
}
