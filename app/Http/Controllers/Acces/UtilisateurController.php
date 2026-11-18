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
    public function index(Request $request): View
    {
        $recherche = trim((string) $request->query('recherche', ''));
        $role = CodeRole::tryFrom((string) $request->query('role', ''));

        $utilisateurs = User::query()
            ->with(['roles', 'structure'])
            ->when($recherche !== '', fn ($query) => $query->where(fn ($q) => $q
                ->whereLike('login', "%{$recherche}%")
                ->orWhereLike('name', "%{$recherche}%")))
            ->when($role !== null, fn ($query) => $query->whereRelation('roles', 'code', $role->value))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('utilisateurs.index', ['utilisateurs' => $utilisateurs, 'recherche' => $recherche, 'role' => $role]);
    }

    public function create(): View
    {
        return view('utilisateurs.formulaire', ['utilisateur' => new User, 'structures' => $this->structures()]);
    }

    public function store(Request $request, EnregistrerUtilisateur $enregistrer): RedirectResponse
    {
        $utilisateur = $enregistrer->handle($this->donnees($request));

        return redirect()->route('utilisateurs.edit', $utilisateur)->with('succes', "Le compte {$utilisateur->login} est créé.");
    }

    public function edit(User $utilisateur): View
    {
        return view('utilisateurs.formulaire', ['utilisateur' => $utilisateur->load(['roles', 'agent']), 'structures' => $this->structures()]);
    }

    public function update(Request $request, User $utilisateur, EnregistrerUtilisateur $enregistrer): RedirectResponse
    {
        if ($utilisateur->is($request->user()) && ! $request->boolean('actif')) {
            throw ValidationException::withMessages(['actif' => 'Vous ne pouvez pas désactiver votre propre compte.']);
        }

        $enregistrer->handle($this->donnees($request), $utilisateur);

        return redirect()->route('utilisateurs.edit', $utilisateur)->with('succes', 'Le compte est enregistré.');
    }

    /**
     * @return array<string, mixed>
     */
    private function donnees(Request $request): array
    {
        $matricule = trim((string) $request->input('matricule_agent', ''));
        $agentId = $matricule === '' ? null : Agent::where('matricule', $matricule)->value('id');

        if ($matricule !== '' && $agentId === null) {
            throw ValidationException::withMessages(['matricule_agent' => "Aucun agent ne porte le matricule {$matricule}."]);
        }

        return [
            'name' => $request->input('name'),
            'login' => $request->input('login'),
            'email' => $request->input('email') ?: null,
            'actif' => $request->boolean('actif'),
            'agent_id' => $agentId,
            'structure_id' => $request->integer('structure_id') ?: null,
            'roles' => array_values((array) $request->input('roles', [])),
        ];
    }

    /**
     * @return Collection<int, Structure>
     */
    private function structures()
    {
        return Structure::actives()->orderBy('libelle')->get(['id', 'code', 'libelle']);
    }
}
