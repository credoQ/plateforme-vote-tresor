<?php

namespace App\Actions\Acces;

use App\Actions\Audit\Journaliser;
use App\Enums\Acces\CodeRole;
use App\Enums\Audit\ActionAuditee;
use App\Models\Agent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Crée ou modifie un compte utilisateur et ses rôles (R1 à R4).
 *
 * Il n'y a pas de mot de passe : la connexion se fait par un code envoyé par e-mail (R42). Un compte doit donc
 * avoir une adresse, la sienne ou celle de la fiche de l'agent lié.
 */
class EnregistrerUtilisateur
{
    public function __construct(private Journaliser $journal) {}

    /**
     * @param  array{name: string, login: string, email?: string|null, actif?: bool, agent_id?: int|null, structure_id?: int|null, roles: list<string>}  $donnees
     *
     * @throws ValidationException
     */
    public function handle(array $donnees, ?User $utilisateur = null): User
    {
        $emailAgent = isset($donnees['agent_id']) ? Agent::whereKey($donnees['agent_id'])->value('email') : null;

        $donnees = Validator::make($donnees, [
            'name' => ['required', 'string', 'max:255'],
            'login' => ['required', 'string', 'max:60', Rule::unique('users', 'login')->ignore($utilisateur?->id)],
            'email' => [Rule::requiredIf(fn (): bool => blank($emailAgent)), 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($utilisateur?->id)],
            'actif' => ['sometimes', 'boolean'],
            'agent_id' => ['nullable', 'integer', 'exists:agents,id', Rule::unique('users', 'agent_id')->ignore($utilisateur?->id)],
            'structure_id' => ['nullable', 'integer', 'exists:structures,id', Rule::requiredIf(fn (): bool => in_array(CodeRole::PointFocal->value, (array) ($donnees['roles'] ?? []), true))],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::enum(CodeRole::class)],
        ], [
            'login.unique' => 'Cet identifiant est déjà utilisé.',
            'email.required' => 'Une adresse e-mail est nécessaire pour recevoir le code de connexion.',
            'email.email' => 'Adresse e-mail invalide.',
            'agent_id.unique' => 'Cet agent dispose déjà d’un compte (R3).',
            'structure_id.required' => 'Un point focal est rattaché à une structure (R4).',
            'roles.required' => 'Un utilisateur possède au moins un rôle (R2).',
            'roles.min' => 'Un utilisateur possède au moins un rôle (R2).',
            'required' => 'Le champ :attribute est obligatoire.',
        ])->validate();

        return DB::transaction(function () use ($donnees, $utilisateur): User {
            $creation = $utilisateur === null;
            $roles = $donnees['roles'];
            unset($donnees['roles']);

            $utilisateur ??= new User;
            $utilisateur->fill($donnees)->save();
            $utilisateur->roles()->sync(Role::whereIn('code', $roles)->pluck('id'));
            $this->journal->handle(ActionAuditee::CompteEnregistre, $utilisateur, ['login' => $utilisateur->login, 'creation' => $creation, 'actif' => $utilisateur->actif, 'roles' => $roles, 'structure_id' => $utilisateur->structure_id]);

            return $utilisateur->load('roles');
        });
    }
}
