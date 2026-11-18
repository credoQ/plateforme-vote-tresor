<?php

namespace App\Actions\Scrutins;

use App\Enums\Scrutins\StatutCandidate;
use App\Models\Candidate;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Désactive une candidate qui ne souhaite pas concourir (R32, R33), jusqu'à l'ouverture du scrutin.
 * La désactivation est tracée : date, auteur et motif facultatif. La candidate reste électrice.
 *
 * Qui peut agir (la candidate, le point focal de sa structure, le comité) relève des droits du module 01.
 */
class DesactiverCandidate
{
    /**
     * @throws ValidationException
     */
    public function handle(Candidate $candidate, ?User $auteur = null, ?string $motif = null): Candidate
    {
        $candidate->loadMissing('scrutin');

        if (! $candidate->scrutin->accepteLesDesistements()) {
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

        return $candidate;
    }
}
