<?php

namespace App\Http\Controllers;

use App\Enums\Editions\StatutPhase;
use App\Models\Inscription;
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

        return view('espace.accueil', [
            'utilisateur' => $utilisateur,
            'affectation' => $utilisateur->agent?->affectationLe(),
            'inscription' => $inscription,
        ]);
    }
}
