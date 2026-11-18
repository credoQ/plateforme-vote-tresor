<?php

namespace App\Actions\Scrutins;

use App\Actions\Audit\Journaliser;
use App\Enums\Audit\ActionAuditee;
use App\Enums\Scrutins\StatutCandidate;
use App\Models\Candidate;
use Illuminate\Validation\ValidationException;

/**
 * Réactive une candidate désactivée tant que le scrutin n'est pas ouvert (R34).
 */
class ReactiverCandidate
{
    public function __construct(private Journaliser $journal) {}

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
        $this->journal->handle(ActionAuditee::CandidateReactivee, $candidate, ['scrutin' => $candidate->scrutin->code, 'matricule' => $candidate->agent?->matricule]);

        return $candidate;
    }
}
