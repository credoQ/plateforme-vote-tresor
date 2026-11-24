<?php

namespace App\Http\Controllers\Referentiel;

use App\Actions\Referentiel\ChangerStatutStructure;
use App\Http\Controllers\Controller;
use App\Models\Structure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Référentiel des structures (module 02) : l'administrateur crée, modifie, désactive et réactive les structures
 * (R5, R8, R9) ; le comité et l'observateur consultent.
 */
class StructureController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Structure::class);

        // La liste et ses filtres sont dans le composant Livewire Referentiel\ListeStructures.
        return view('referentiel.structures.index');
    }

    /**
     * Ancienne adresse des types et départements : ils forment désormais un onglet de la liste des structures.
     */
    public function organisation(): RedirectResponse
    {
        Gate::authorize('viewAny', Structure::class);

        return redirect()->route('structures.index', ['onglet' => 'organisation']);
    }

    /**
     * Formulaire d'une nouvelle structure, éventuellement un service à rattacher (paramètre « parente ») ;
     * l'envoi passe par le composant Livewire Referentiel\FormulaireStructure.
     */
    public function create(Request $request): View
    {
        Gate::authorize('create', Structure::class);

        return view('referentiel.structures.formulaire', ['structure' => new Structure, 'parente' => $request->integer('parente') ?: null]);
    }

    public function show(Request $request, Structure $structure): View
    {
        Gate::authorize('view', $structure);

        return view('referentiel.structures.show', [
            'structure' => $structure->load(['typeStructure.poleElectoral', 'departement', 'structureParente']),
            'agentsEnService' => $structure->affectations()->enCoursLe()->count(),
        ]);
    }

    public function edit(Structure $structure): View
    {
        Gate::authorize('update', $structure);

        return view('referentiel.structures.formulaire', ['structure' => $structure, 'parente' => null]);
    }

    public function desactiver(Request $request, Structure $structure, ChangerStatutStructure $changer): RedirectResponse
    {
        Gate::authorize('update', $structure);
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']], ['max' => 'Le motif ne dépasse pas 500 caractères.']);

        $changer->desactiver($structure, $donnees['motif'] ?? null);

        return redirect()->route('structures.show', $structure)->with('succes', "La structure {$structure->code} est désactivée.");
    }

    public function reactiver(Structure $structure, ChangerStatutStructure $changer): RedirectResponse
    {
        Gate::authorize('update', $structure);

        $changer->reactiver($structure);

        return redirect()->route('structures.show', $structure)->with('succes', "La structure {$structure->code} est réactivée.");
    }
}
