<?php

namespace App\Http\Controllers\Suivi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Suivi de la participation (module 08) : comité, observateur, administrateur, et point focal limité à sa
 * structure (R4). Les indicateurs, graphiques et filtres sont dans le composant Livewire Suivi\TableauDeBord.
 */
class TableauDeBordController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()->can('observer') || $request->user()->can('controler-listes'), 403);

        return view('suivi.tableau-de-bord');
    }
}
