<?php

namespace App\Http\Controllers\Scrutins;

use App\Actions\Scrutins\NominerCandidate;
use App\Actions\Scrutins\RetirerNomination;
use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Scrutin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Nominations saisies par le comité sur la fiche d'un scrutin dont le collège est à nomination (R28, R58).
 */
class NominationController extends Controller
{
    public function store(Request $request, Scrutin $scrutin, NominerCandidate $nominer): RedirectResponse
    {
        $donnees = $request->validate([
            'matricule' => ['required', 'string', 'max:30'],
            'reference_nomination' => ['required', 'string', 'max:200'],
        ], [
            'matricule.required' => 'Indiquez le matricule de la candidate.',
            'reference_nomination.required' => 'Indiquez la référence de la décision de nomination (procès-verbal, fiche).',
            'reference_nomination.max' => 'La référence ne dépasse pas 200 caractères.',
        ]);

        $candidate = $nominer->handle($scrutin, $donnees['matricule'], $donnees['reference_nomination'], $request->user());

        return redirect()->route('scrutins.show', $scrutin)->with('succes', "{$candidate->agent->nomComplet()} est nominée.");
    }

    public function destroy(Request $request, Candidate $candidate, RetirerNomination $retirer): RedirectResponse
    {
        $retirer->handle($candidate, $request->user());

        return redirect()->route('scrutins.show', $candidate->scrutin_id)->with('succes', "La nomination de {$candidate->agent->nomComplet()} est retirée.");
    }
}
