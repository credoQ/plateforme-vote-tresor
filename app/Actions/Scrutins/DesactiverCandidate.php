<?php

namespace App\Actions\Scrutins;

use App\Actions\Audit\Journaliser;
use App\Enums\Audit\ActionAuditee;
use App\Enums\Scrutins\StatutCandidate;
use App\Models\Candidate;
use App\Models\Scrutin;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Désactive une candidate qui ne souhaite pas concourir (R32, R33), jusqu'à l'ouverture du scrutin.
 * La désactivation est tracée : date, auteur et motif facultatif. La candidate reste électrice.
 *
 * Qui peut agir (la candidate, le point focal de sa structure, le comité) relève des droits du module 01.
 */
class DesactiverCandidate
{
    public function __construct(private Journaliser $journal) {}

    /**
     * @throws ValidationException
     */
    public function handle(Candidate $candidate, ?User $auteur = null, ?string $motif = null): Candidate
    {
        // Le scrutin est verrouillé : sa bascule à l'ouverture attend la fin de la désactivation (R33).
        return DB::transaction(function () use ($candidate, $auteur, $motif): Candidate {
            $scrutin = Scrutin::lockForUpdate()->findOrFail($candidate->scrutin_id);
            $candidate = Candidate::lockForUpdate()->findOrFail($candidate->id)->setRelation('scrutin', $scrutin);

            if (! $scrutin->accepteLesDesistements()) {
                throw ValidationException::withMessages(['candidate' => 'Le scrutin est ouvert : la candidate ne peut plus être désactivée (R33).']);
            }

            if (! $candidate->estActive()) {
                throw ValidationException::withMessages(['candidate' => 'La candidate est déjà désactivée.']);
            }

            $candidate->update([
                'statut' => StatutCandidate::Desactivee,
                'date_desistement' => now(),
                'motif_desistement' => filled($motif) ? mb_substr(trim($motif), 0, 500) : null,
                'desactivee_par_id' => $auteur?->id,
            ]);
            $this->journal->handle(ActionAuditee::CandidateDesactivee, $candidate, ['scrutin' => $scrutin->code, 'matricule' => $candidate->agent?->matricule, 'motif' => $candidate->motif_desistement]);

            return $candidate;
        });
    }
}
