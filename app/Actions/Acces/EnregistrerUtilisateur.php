<?php

namespace App\Actions\Acces;

use App\Actions\Audit\Journaliser;
use App\Enums\Acces\CodeRole;
use App\Enums\Audit\ActionAuditee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Crée ou modifie un compte utilisateur et ses rôles (R1 à R4).
 *
 * À la création, un mot de passe provisoire est généré et devra être changé à la première connexion.
 */
class EnregistrerUtilisateur
{
    public function __construct(private MotDePasseProvisoire $motDePasse, private Journaliser $journal) {}

    /**
     * @param  array{name: string, login: string, email?: string|null, actif?: bool, agent_id?: int|null, structure_id?: int|null, roles: list<string>}  $donnees
     * @return array{utilisateur: User, mot_de_passe: string|null}
     *
     * @throws ValidationException
     */
    public function handle(array $donnees, ?User $utilisateur = null): array
    {
        $donnees = Validator::make($donnees, [
            'name' => ['required', 'string', 'max:255'],
            'login' => ['required', 'string', 'max:60', Rule::unique('users', 'login')->ignore($utilisateur?->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($utilisateur?->id)],
            'actif' => ['sometimes', 'boolean'],
            'agent_id' => ['nullable', 'integer', 'exists:agents,id', Rule::unique('users', 'agent_id')->ignore($utilisateur?->id)],
            'structure_id' => ['nullable', 'integer', 'exists:structures,id', Rule::requiredIf(fn (): bool => in_array(CodeRole::PointFocal->value, (array) ($donnees['roles'] ?? []), true))],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::enum(CodeRole::class)],
        ], [
            'login.unique' => 'Cet identifiant est déjà utilisé.',
            'agent_id.unique' => 'Cet agent dispose déjà d’un compte (R3).',
            'structure_id.required' => 'Un point focal est rattaché à une structure (R4).',
            'roles.required' => 'Un utilisateur possède au moins un rôle (R2).',
            'roles.min' => 'Un utilisateur possède au moins un rôle (R2).',
            'required' => 'Le champ :attribute est obligatoire.',
        ])->validate();

        return DB::transaction(function () use ($donnees, $utilisateur): array {
            $motDePasse = null;
            $roles = $donnees['roles'];
            unset($donnees['roles']);

            if ($utilisateur === null) {
                $motDePasse = $this->motDePasse->generer();
                $utilisateur = new User([...$donnees, 'password' => $motDePasse, 'mot_de_passe_a_changer' => true]);
            } else {
                $utilisateur->fill($donnees);
            }

            $utilisateur->save();
            $utilisateur->roles()->sync(Role::whereIn('code', $roles)->pluck('id'));
            $this->journal->handle(ActionAuditee::CompteEnregistre, $utilisateur, ['login' => $utilisateur->login, 'creation' => $motDePasse !== null, 'actif' => $utilisateur->actif, 'roles' => $roles, 'structure_id' => $utilisateur->structure_id]);

            return ['utilisateur' => $utilisateur->load('roles'), 'mot_de_passe' => $motDePasse];
        });
    }
}
