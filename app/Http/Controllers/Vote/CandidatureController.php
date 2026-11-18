<?php

namespace App\Http\Controllers\Vote;

use App\Actions\Scrutins\DesactiverCandidate;
use App\Actions\Scrutins\ReactiverCandidate;
use App\Http\Controllers\Controller;
use App\Models\Candidate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Désistement et réactivation d'une candidate (R32 à R34), depuis son espace ou par le point focal ou le comité.
 */
class CandidatureController extends Controller
{
    public function desister(Request $request, Candidate $candidate, DesactiverCandidate $desactiver): RedirectResponse
    {
        Gate::authorize('desactiver', $candidate);
        $motif = $request->validate(['motif' => ['nullable', 'string', 'max:500']], ['motif.max' => 'Le motif ne dépasse pas 500 caractères.'])['motif'] ?? null;

        $desactiver->handle($candidate, $request->user(), $motif);

        return back()->with('succes', $candidate->agent_id === $request->user()->agent_id
            ? 'Votre candidature est retirée. Vous restez électrice.'
            : 'La candidate est désactivée.');
    }

    public function reactiver(Request $request, Candidate $candidate, ReactiverCandidate $reactiver): RedirectResponse
    {
        Gate::authorize('reactiver', $candidate);

        $reactiver->handle($candidate);

        return back()->with('succes', 'La candidature est réactivée.');
    }
}
