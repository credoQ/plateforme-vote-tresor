<?php

namespace App\Http\Controllers\Suivi;

use App\Actions\Suivi\MesurerParticipation;
use App\Enums\Acces\CodeRole;
use App\Enums\Editions\StatutEdition;
use App\Enums\Editions\StatutPhase;
use App\Http\Controllers\Controller;
use App\Models\Edition;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Suivi de la participation de la phase en cours (module 08) : comité, observateur, administrateur,
 * et point focal limité à sa structure (R4). Aucune voix n'est affichée (R46).
 */
class TableauDeBordController extends Controller
{
    public function __invoke(Request $request, MesurerParticipation $mesurer): View
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur->can('observer') || $utilisateur->can('controler-listes'), 403);

        $edition = Edition::with('phases')->where('statut', StatutEdition::EnCours->value)->first()
            ?? Edition::with('phases')->orderByDesc('annee')->first();

        $phase = $edition?->phases->firstWhere('statut', StatutPhase::Ouverte)
            ?? $edition?->phases->where('statut', StatutPhase::Cloturee)->last()
            ?? $edition?->phases->first();

        $limite = $utilisateur->aLeRole(CodeRole::Comite, CodeRole::Observateur, CodeRole::Administrateur) ? null : $utilisateur->perimetre();

        return view('suivi.tableau-de-bord', [
            'edition' => $edition,
            'phase' => $phase,
            'participation' => $phase === null ? null : $mesurer->handle($phase, $limite),
            'restreint' => $limite !== null,
        ]);
    }
}
