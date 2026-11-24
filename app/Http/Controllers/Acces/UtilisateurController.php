<?php

namespace App\Http\Controllers\Acces;

use App\Actions\Acces\EnregistrerUtilisateur;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Administration des comptes utilisateurs et de leurs rôles (R1 à R4), réservée à l'administrateur.
 */
class UtilisateurController extends Controller
{
    public function index(): View
    {
        // La liste et ses filtres sont dans le composant Livewire Acces\ListeUtilisateurs ; la fenêtre de liaison
        // propose les agents qui n'ont pas encore de compte.
        return view('utilisateurs.index', [
            'agentsSansCompte' => Agent::query()->whereDoesntHave('compte')->orderBy('nom')->orderBy('prenoms')->get(['id', 'matricule', 'nom', 'prenoms']),
        ]);
    }

    /**
     * Pages de création et de modification : le formulaire est le composant Livewire Acces\FormulaireUtilisateur.
     */
    public function create(): View
    {
        return view('utilisateurs.formulaire', ['utilisateur' => new User]);
    }

    public function edit(User $utilisateur): View
    {
        return view('utilisateurs.formulaire', ['utilisateur' => $utilisateur]);
    }

    /**
     * Lie un compte sans agent à la fiche d'un agent qui n'a pas encore de compte (R3).
     */
    public function lierAgent(Request $request, User $utilisateur, EnregistrerUtilisateur $enregistrer): RedirectResponse
    {
        $matricule = strtok(trim((string) $request->input('agent', '')), ' ') ?: '';
        $agent = Agent::where('matricule', $matricule)->first();

        if ($utilisateur->agent_id !== null) {
            throw ValidationException::withMessages(['agent' => 'Ce compte est déjà lié à un agent.']);
        }
        if ($agent === null) {
            throw ValidationException::withMessages(['agent' => 'Choisissez un agent de la liste.']);
        }
        if ($agent->compte()->exists()) {
            throw ValidationException::withMessages(['agent' => "L’agent {$agent->nomComplet()} dispose déjà d’un compte."]);
        }

        $utilisateur->load('roles');
        $enregistrer->handle([
            'name' => $utilisateur->name,
            'login' => $utilisateur->login,
            'email' => $utilisateur->email,
            'actif' => $utilisateur->actif,
            'agent_id' => $agent->id,
            'structure_id' => $utilisateur->structure_id,
            'roles' => $utilisateur->roles->map(fn ($role) => $role->code->value)->all(),
        ], $utilisateur);

        return redirect()->route('utilisateurs.index')->with('succes', "Le compte {$utilisateur->login} est lié à l’agent {$agent->nomComplet()}.");
    }
}
