<?php

namespace App\Actions\Scrutins;

use App\Actions\Audit\Journaliser;
use App\Enums\Audit\ActionAuditee;
use App\Enums\Scrutins\OrigineCandidate;
use App\Models\Candidate;
use App\Models\Scrutin;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Le comité retire une nomination saisie par erreur (R58), jusqu'à l'arrêt des listes. Le retrait est tracé.
 * Pour une nominée qui renonce à concourir, on la désactive plutôt (R32) : le désistement reste visible.
 */
class RetirerNomination
{
    public function __construct(private Journaliser $journal) {}

    /**
     * @throws ValidationException
     */
    public function handle(Candidate $candidate, User $auteur): void
    {
        DB::transaction(function () use ($candidate, $auteur): void {
            $scrutin = Scrutin::lockForUpdate()->findOrFail($candidate->scrutin_id);
            $candidate = Candidate::with('agent')->lockForUpdate()->findOrFail($candidate->id);

            if ($candidate->origine !== OrigineCandidate::Nomination) {
                throw ValidationException::withMessages(['candidate' => 'Seule une candidate nominée par le comité peut voir sa nomination retirée.']);
            }

            if (! $scrutin->listesSontModifiables()) {
                throw ValidationException::withMessages(['candidate' => 'Les listes du scrutin sont arrêtées : rouvrez-les pour retirer une nomination.']);
            }

            $this->journal->handle(ActionAuditee::NominationRetiree, $candidate, ['scrutin' => $scrutin->code, 'matricule' => $candidate->agent->matricule, 'reference' => $candidate->reference_nomination], $auteur);
            $candidate->delete();
        });
    }
}
