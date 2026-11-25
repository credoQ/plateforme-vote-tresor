<?php

namespace App\Livewire\Acces;

use App\Actions\Acces\EnregistrerUtilisateur;
use App\Enums\Acces\CodeRole;
use App\Livewire\Concerns\SignaleLesErreurs;
use App\Models\Agent;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Création et modification d'un compte utilisateur et de ses rôles (R1 à R4), envoyées sans recharger la page.
 * La validation reste dans l'action EnregistrerUtilisateur ; le composant prépare la saisie et affiche les erreurs.
 */
class FormulaireUtilisateur extends Component
{
    use SignaleLesErreurs;

    public ?User $utilisateur = null;

    public string $name = '';

    public string $login = '';

    public string $email = '';

    public bool $actif = true;

    /**
     * @var list<string>
     */
    public array $roles = [];

    public string $structureId = '';

    /**
     * Contrôle des droits à chaque requête du composant, envoi compris.
     */
    public function boot(): void
    {
        Gate::authorize('administrer');
    }

    public function mount(?User $utilisateur = null): void
    {
        $this->utilisateur = $utilisateur?->exists ? $utilisateur->load(['roles', 'agent']) : null;

        if ($this->utilisateur !== null) {
            $this->name = $this->utilisateur->name;
            $this->login = $this->utilisateur->login;
            // Compte sans adresse : celle de la fiche de l'agent lié est proposée.
            $this->email = (string) ($this->utilisateur->email ?? $this->utilisateur->agent?->email);
            $this->actif = $this->utilisateur->actif;
            $this->roles = $this->utilisateur->roles->map(fn ($role): string => $role->code->value)->values()->all();
            $this->structureId = (string) $this->utilisateur->structure_id;
        }
    }

    /**
     * @return Collection<int, Structure>
     */
    #[Computed]
    public function structures(): Collection
    {
        return Structure::actives()->orderBy('libelle')->get(['id', 'code', 'libelle']);
    }

    public function enregistrer(EnregistrerUtilisateur $enregistrer): mixed
    {
        $this->resetErrorBag();

        try {
            if ($this->utilisateur?->is(Auth::user()) && ! $this->actif) {
                throw ValidationException::withMessages(['actif' => 'Vous ne pouvez pas désactiver votre propre compte.']);
            }

            $roles = array_values($this->roles);
            $compte = $enregistrer->handle([
                'name' => $this->name,
                'login' => trim($this->login),
                'email' => trim($this->email) ?: null,
                'actif' => $this->actif,
                'agent_id' => $this->agentDuMatricule(trim($this->login)),
                // La structure ne concerne que le point focal (R4) : elle est ignorée pour les autres rôles.
                'structure_id' => in_array(CodeRole::PointFocal->value, $roles, true) ? ((int) $this->structureId ?: null) : null,
                'roles' => $roles,
            ], $this->utilisateur);
        } catch (ValidationException $exception) {
            $this->signalerErreurs($exception);

            return null;
        }

        session()->flash('succes', $this->utilisateur === null ? "Le compte {$compte->login} est créé." : 'Le compte est enregistré.');

        return $this->redirectRoute('utilisateurs.edit', $compte, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.acces.formulaire-utilisateur');
    }

    /**
     * Agent lié d'après l'identifiant (R3) : l'agent qui porte ce matricule, s'il n'a pas déjà un autre compte ;
     * à défaut d'agent, le compte garde l'agent auquel il était lié.
     *
     * @throws ValidationException
     */
    private function agentDuMatricule(string $login): ?int
    {
        $agent = $login === '' ? null : Agent::where('matricule', $login)->first();

        if ($agent === null) {
            return $this->utilisateur?->agent_id;
        }

        if (User::where('agent_id', $agent->id)->when($this->utilisateur, fn ($query) => $query->whereKeyNot($this->utilisateur->id))->exists()) {
            throw ValidationException::withMessages(['login' => "L’agent {$agent->nomComplet()} ({$login}) dispose déjà d’un compte."]);
        }

        return $agent->id;
    }
}
