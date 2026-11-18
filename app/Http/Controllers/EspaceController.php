<?php

namespace App\Http\Controllers;

use App\Enums\Editions\StatutEdition;
use App\Enums\Editions\StatutPhase;
use App\Enums\Scrutins\StatutScrutin;
use App\Models\Candidate;
use App\Models\Inscription;
use App\Models\Scrutin;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Page d'accueil de l'utilisateur connecté : son profil et, pour un électeur, son scrutin de la phase ouverte.
 */
class EspaceController extends Controller
{
    public function __invoke(Request $request): View
    {
        $utilisateur = $request->user()->load(['roles', 'agent', 'structure']);

        $inscription = $utilisateur->agent_id === null ? null : Inscription::query()
            ->with('scrutin.phase.edition')
            ->where('agent_id', $utilisateur->agent_id)
            ->whereHas('scrutin.phase', fn ($phase) => $phase->where('statut', StatutPhase::Ouverte->value))
            ->first();

        // Annonces de la plateforme (module 08) : scrutins ouverts et résultats publiés de l'édition en cours.
        $annonces = Scrutin::query()
            ->whereHas('phase.edition', fn ($edition) => $edition->where('statut', StatutEdition::EnCours->value))
            ->whereIn('statut', [StatutScrutin::Ouvert->value, StatutScrutin::ResultatsPublies->value])
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        // Candidatures de l'agent qui acceptent encore un désistement ou une réactivation (R32 à R34).
        $candidatures = $utilisateur->agent_id === null ? collect() : Candidate::query()
            ->with('scrutin.phase')
            ->where('agent_id', $utilisateur->agent_id)
            ->whereHas('scrutin', fn ($scrutin) => $scrutin
                ->whereIn('statut', [StatutScrutin::EnPreparation->value, StatutScrutin::ListesArretees->value])
                ->whereHas('phase.edition', fn ($edition) => $edition->where('statut', StatutEdition::EnCours->value)))
            ->get();

        return view('espace.accueil', [
            'utilisateur' => $utilisateur,
            'affectation' => $utilisateur->agent?->affectationLe(),
            'inscription' => $inscription,
            'annonces' => $annonces,
            'candidatures' => $candidatures,
        ]);
    }
}
