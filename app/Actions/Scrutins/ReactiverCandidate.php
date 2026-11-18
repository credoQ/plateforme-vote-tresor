<?php

namespace App\Actions\Scrutins;

use App\Enums\Scrutins\StatutCandidate;
use App\Models\Candidate;
use Illuminate\Validation\ValidationException;

/**
 * Réactive une candidate désactivée tant que le scrutin n'est pas ouvert (R34).
 */
class ReactiverCandidate
{
    /**
     * @throws ValidationException
     */
    public function handle(Candidate $candidate): Candidate
    {
        $candidate->loadMissing('scrutin');

        if (! $candidate->scrutin->accepteLesDesistements()) {
            throw ValidationException::withMessages(['candidate' => 'Le scrutin est ouvert : la candidate ne peut plus être réactivée (R34).']);
        }

        if ($candidate->estActive()) {
            throw ValidationException::withMessages(['candidate' => 'La candidate est déjà active.']);
        }

        $candidate->update([
            'statut' => StatutCandidate::Active,
            'date_desistement' => null,
            'motif_desistement' => null,
            'desactivee_par_id' => null,
        ]);

        return $candidate;
    }
}
