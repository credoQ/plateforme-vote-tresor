<?php

namespace App\Policies;

use App\Enums\Acces\CodeRole;
use App\Models\Structure;
use App\Models\User;

/**
 * Référentiel des structures (module 02) : l'administrateur le tient à jour, le comité et l'observateur le consultent.
 */
class StructurePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->aLeRole(CodeRole::Administrateur, CodeRole::Comite, CodeRole::Observateur);
    }

    public function view(User $user, Structure $structure): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->aLeRole(CodeRole::Administrateur);
    }

    public function update(User $user, Structure $structure): bool
    {
        return $user->aLeRole(CodeRole::Administrateur);
    }
}
