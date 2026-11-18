<?php

namespace App\Policies;

use App\Enums\Acces\CodeRole;
use App\Models\Inscription;
use App\Models\User;

/**
 * Contrôle des listes électorales : le comité voit tout, le point focal les inscrits de sa structure (R4, R27).
 */
class InscriptionPolicy
{
    public function view(User $user, Inscription $inscription): bool
    {
        if ($user->aLeRole(CodeRole::Comite, CodeRole::Observateur)) {
            return true;
        }

        return $user->aLeRole(CodeRole::PointFocal) && in_array($inscription->structure_id, $user->perimetre(), true);
    }
}
