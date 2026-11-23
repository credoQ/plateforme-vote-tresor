<?php

namespace App\Http\Controllers\Scrutins;

use App\Actions\Scrutins\NominerCandidate;
use App\Actions\Scrutins\RetirerNomination;
use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Scrutin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Nominations saisies par le comité sur la fiche d'un scrutin dont le collège est à nomination (R28, R58).
 */
class NominationController extends Controller
{
    public function store(Request $request, Scrutin $scrutin, NominerCandidate $nominer): RedirectResponse
    {
        $donnees = $request->validate([
            'matricules' => ['required', 'array', 'min:1'],
            'matricules.*' => ['required', 'string', 'max:30', 'distinct'],
            'reference_nomination' => ['required', 'string', 'max:200'],
        ], [
            'matricules.required' => 'Choisissez au moins une candidate.',
            'matricules.min' => 'Choisissez au moins une candidate.',
            'reference_nomination.required' => 'Indiquez la référence de la décision de nomination (procès-verbal, fiche).',
            'reference_nomination.max' => 'La référence ne dépasse pas 200 caractères.',
        ]);

        // Une même décision peut nominer plusieurs candidates : toutes sont enregistrées, ou aucune.
        $candidates = DB::transaction(fn () => collect($donnees['matricules'])
            ->map(fn (string $matricule): Candidate => $nominer->handle($scrutin, $matricule, $donnees['reference_nomination'], $request->user())));

        $message = $candidates->count() === 1
            ? "{$candidates->first()->agent->nomComplet()} est nominée."
            : "{$candidates->count()} candidates sont nominées.";

        return redirect()->route('scrutins.show', $scrutin)->with('succes', $message);
    }

    public function destroy(Request $request, Candidate $candidate, RetirerNomination $retirer): RedirectResponse
    {
        $retirer->handle($candidate, $request->user());

        return redirect()->route('scrutins.show', $candidate->scrutin_id)->with('succes', "La nomination de {$candidate->agent->nomComplet()} est retirée.");
    }
}
