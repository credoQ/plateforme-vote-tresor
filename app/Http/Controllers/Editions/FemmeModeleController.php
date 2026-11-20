<?php

namespace App\Http\Controllers\Editions;

use App\Actions\Editions\AjouterFemmeModele;
use App\Actions\Editions\SupprimerFemmeModele;
use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\FemmeModele;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Historique des femmes modèles qui fonde l'inéligibilité (R59) : consultation, ajout par le comité des femmes
 * modèles des éditions antérieures, retrait d'une ligne saisie par erreur.
 */
class FemmeModeleController extends Controller
{
    public function index(Request $request): View
    {
        $edition = Edition::courante();
        $ineligibles = $edition?->ineligibles() ?? collect();
        $recherche = trim((string) $request->query('recherche', ''));
        $annee = $request->integer('annee') ?: null;

        $femmesModeles = FemmeModele::query()
            ->when($recherche !== '', fn ($q) => $q->where(fn ($r) => $r
                ->whereLike('nom_complet', "%{$recherche}%")->orWhereLike('matricule', "%{$recherche}%")))
            ->when($annee !== null, fn ($q) => $q->where('annee', $annee))
            ->orderByDesc('annee')->orderBy('nom_complet')
            ->paginate(config('app.lignes_par_page'))
            ->withQueryString();

        return view('femmes-modeles.index', [
            'femmesModeles' => $femmesModeles,
            'annees' => FemmeModele::query()->distinct()->orderByDesc('annee')->pluck('annee'),
            'recherche' => $recherche,
            'annee' => $annee,
            'edition' => $edition,
            'ineligibles' => $ineligibles,
        ]);
    }

    public function create(): View
    {
        return view('femmes-modeles.formulaire', ['anneeMaximale' => now()->year, 'anneeMinimale' => AjouterFemmeModele::ANNEE_MINIMALE]);
    }

    public function store(Request $request, AjouterFemmeModele $ajouter): RedirectResponse
    {
        $femmeModele = $ajouter->handle([
            'matricule' => (string) $request->input('matricule', ''),
            'annee' => $request->input('annee'),
            'nom' => $request->input('nom'),
            'scrutin' => $request->input('scrutin'),
        ]);

        return redirect()->route('femmes-modeles.index')->with('succes', "{$femmeModele->nom_complet} ({$femmeModele->annee}) est ajoutée à l’historique.");
    }

    public function destroy(FemmeModele $femmeModele, SupprimerFemmeModele $supprimer): RedirectResponse
    {
        $supprimer->handle($femmeModele);

        return redirect()->route('femmes-modeles.index')->with('succes', "{$femmeModele->nom_complet} ({$femmeModele->annee}) est retirée de l’historique.");
    }
}
