<?php

namespace App\Policies;

use App\Enums\Acces\CodeRole;
use App\Models\Agent;
use App\Models\User;

/**
 * Consultation et mise à jour des agents : l'administrateur gère, le comité et l'observateur consultent,
 * le point focal ne voit que les agents de sa structure (R4).
 */
class AgentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->aLeRole(CodeRole::Administrateur, CodeRole::Comite, CodeRole::Observateur, CodeRole::PointFocal);
    }

    public function view(User $user, Agent $agent): bool
    {
        if ($user->aLeRole(CodeRole::Administrateur, CodeRole::Comite, CodeRole::Observateur)) {
            return true;
        }

        $structureId = $agent->affectationLe()?->structure_id;

        return $user->aLeRole(CodeRole::PointFocal) && $structureId !== null && in_array($structureId, $user->perimetre(), true);
    }

    public function update(User $user, Agent $agent): bool
    {
        return $user->aLeRole(CodeRole::Administrateur);
    }
}
