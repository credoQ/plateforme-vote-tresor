<?php

namespace App\Actions\Acces;

use App\Enums\Acces\CodeRole;
use App\Models\Agent;
use App\Models\Role;
use App\Models\User;

/**
 * Ouvre le compte électeur d'un agent (R1, R3) : identifiant = matricule, rôle Électeur, sans mot de passe (R42).
 *
 * - Un agent qui a déjà un compte le garde ; il reçoit le rôle Électeur s'il ne l'a pas.
 * - Un compte existant sous ce matricule et lié à aucun agent (un administrateur créé avant l'import, par exemple)
 *   est rattaché à l'agent plutôt que doublé : un agent a au plus un compte.
 * - Un compte de ce matricule déjà lié à un autre agent n'est pas touché : aucun compte n'est ouvert.
 */
class OuvrirCompteElecteur
{
    public function handle(Agent $agent): ?User
    {
        $compte = $agent->compte()->first() ?? User::where('login', $agent->matricule)->first();

        if ($compte !== null && $compte->agent_id !== null && $compte->agent_id !== $agent->id) {
            return null;
        }

        if ($compte === null) {
            $compte = User::create(['name' => $agent->nomComplet(), 'login' => $agent->matricule, 'agent_id' => $agent->id]);
        } elseif ($compte->agent_id === null) {
            $compte->update(['agent_id' => $agent->id]);
        }

        $compte->roles()->syncWithoutDetaching([Role::where('code', CodeRole::Electeur->value)->firstOrFail()->id]);

        return $compte;
    }
}
