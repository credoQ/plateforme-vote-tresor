<?php

namespace App\Http\Controllers\Acces;

use App\Actions\Acces\EnregistrerUtilisateur;
use App\Enums\Acces\CodeRole;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

    public function create(): View
    {
        return view('utilisateurs.formulaire', ['utilisateur' => new User, 'rolesDuCompte' => [], 'structures' => $this->structures(), 'pointFocal' => $this->pointFocal([])]);
    }

    public function store(Request $request, EnregistrerUtilisateur $enregistrer): RedirectResponse
    {
        $utilisateur = $enregistrer->handle($this->donnees($request));

        return redirect()->route('utilisateurs.edit', $utilisateur)->with('succes', "Le compte {$utilisateur->login} est créé.");
    }

    public function edit(User $utilisateur): View
    {
        $roles = $utilisateur->load(['roles', 'agent'])->roles->map(fn ($role) => $role->code->value)->all();

        return view('utilisateurs.formulaire', [
            'utilisateur' => $utilisateur,
            'rolesDuCompte' => $roles,
            'structures' => $this->structures(),
            'pointFocal' => $this->pointFocal($roles),
        ]);
    }

    public function update(Request $request, User $utilisateur, EnregistrerUtilisateur $enregistrer): RedirectResponse
    {
        if ($utilisateur->is($request->user()) && ! $request->boolean('actif')) {
            throw ValidationException::withMessages(['actif' => 'Vous ne pouvez pas désactiver votre propre compte.']);
        }

        $enregistrer->handle($this->donnees($request, $utilisateur), $utilisateur);

        return redirect()->route('utilisateurs.edit', $utilisateur)->with('succes', 'Le compte est enregistré.');
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

    /**
     * @return array<string, mixed>
     */
    private function donnees(Request $request, ?User $utilisateur = null): array
    {
        $roles = array_values((array) $request->input('roles', []));

        return [
            'name' => $request->input('name'),
            'login' => $request->input('login'),
            'email' => $request->input('email') ?: null,
            'actif' => $request->boolean('actif'),
            'agent_id' => $this->agentDuMatricule(trim((string) $request->input('login', '')), $utilisateur),
            // La structure ne concerne que le point focal (R4) : elle est ignorée pour les autres rôles.
            'structure_id' => in_array(CodeRole::PointFocal->value, $roles, true) ? ($request->integer('structure_id') ?: null) : null,
            'roles' => $roles,
        ];
    }

    /**
     * Agent lié d'après l'identifiant (R3) : l'agent qui porte ce matricule, s'il n'a pas déjà un autre compte ;
     * à défaut d'agent, le compte garde l'agent auquel il était lié.
     *
     * @throws ValidationException
     */
    private function agentDuMatricule(string $login, ?User $utilisateur): ?int
    {
        $agent = $login === '' ? null : Agent::where('matricule', $login)->first();

        if ($agent === null) {
            return $utilisateur?->agent_id;
        }

        if (User::where('agent_id', $agent->id)->when($utilisateur, fn ($query) => $query->whereKeyNot($utilisateur->id))->exists()) {
            throw ValidationException::withMessages(['login' => "L’agent {$agent->nomComplet()} ({$login}) dispose déjà d’un compte."]);
        }

        return $agent->id;
    }

    /**
     * Le rôle point focal est-il coché (saisie renvoyée après une erreur, sinon rôles du compte) ? La structure du
     * point focal ne s'affiche qu'avec lui.
     *
     * @param  list<string>  $rolesDuCompte
     */
    private function pointFocal(array $rolesDuCompte): bool
    {
        return in_array(CodeRole::PointFocal->value, (array) old('roles', $rolesDuCompte), true);
    }

    /**
     * @return Collection<int, Structure>
     */
    private function structures()
    {
        return Structure::actives()->orderBy('libelle')->get(['id', 'code', 'libelle']);
    }
}
