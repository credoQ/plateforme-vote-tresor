<?php

namespace App\Policies;

use App\Enums\Acces\CodeRole;
use App\Models\Candidate;
use App\Models\Inscription;
use App\Models\User;

/**
 * Désactivation et réactivation d'une candidate (R32, R34) : par elle-même, par le comité, par l'administrateur,
 * ou par le point focal de la structure où elle est inscrite (R4).
 */
class CandidatePolicy
{
    public function desactiver(User $user, Candidate $candidate): bool
    {
        if ($user->agent_id !== null && $user->agent_id === $candidate->agent_id) {
            return true;
        }

        if ($user->aLeRole(CodeRole::Comite, CodeRole::Administrateur)) {
            return true;
        }

        if (! $user->aLeRole(CodeRole::PointFocal)) {
            return false;
        }

        $structureId = Inscription::where('scrutin_id', $candidate->scrutin_id)
            ->where('agent_id', $candidate->agent_id)
            ->value('structure_id')
            ?? $candidate->agent?->affectationLe($candidate->scrutin?->phase?->edition?->dateDeReference())?->structure_id;

        return $structureId !== null && in_array($structureId, $user->perimetre(), true);
    }

    public function reactiver(User $user, Candidate $candidate): bool
    {
        return $this->desactiver($user, $candidate);
    }
}
