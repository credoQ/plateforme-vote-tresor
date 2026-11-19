<?php

namespace App\Actions\Scrutins;

use App\Actions\Audit\Journaliser;
use App\Enums\Audit\ActionAuditee;
use App\Enums\Scrutins\OrigineCandidate;
use App\Models\Agent;
use App\Models\Candidate;
use App\Models\Scrutin;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Le comité saisit une nominée sur un scrutin dont le collège est à nomination (R28, R58), jusqu'à l'arrêt des listes.
 *
 * La nominée est une agent de sexe féminin inscrite sur la liste électorale du scrutin, éligible (R59) ; la référence de la décision
 * (procès-verbal, fiche de nomination) est obligatoire. Le nombre de nominées n'est pas limité. La saisie est tracée.
 */
class NominerCandidate
{
    public function __construct(private Journaliser $journal) {}

    /**
     * @throws ValidationException
     */
    public function handle(Scrutin $scrutin, string $matricule, string $reference, User $auteur): Candidate
    {
        $reference = trim($reference);
        if ($reference === '') {
            $this->echouer('reference_nomination', 'Indiquez la référence de la décision de nomination (procès-verbal, fiche).');
        }

        return DB::transaction(function () use ($scrutin, $matricule, $reference, $auteur): Candidate {
            $scrutin = Scrutin::with(['college', 'phase.edition'])->lockForUpdate()->findOrFail($scrutin->id);

            if ($scrutin->college->origine_candidates !== OrigineCandidate::Nomination) {
                $this->echouer('matricule', 'Les candidates de ce scrutin ne sont pas nominées par le comité.');
            }

            if (! $scrutin->listesSontModifiables()) {
                $this->echouer('matricule', 'Les listes du scrutin sont arrêtées : rouvrez-les pour nominer une candidate.');
            }

            $agent = Agent::where('matricule', trim($matricule))->first();
            if ($agent === null || ! $scrutin->inscriptions()->where('agent_id', $agent->id)->exists()) {
                $this->echouer('matricule', "Aucune agent de matricule « {$matricule} » n’est inscrite sur la liste électorale de ce scrutin.");
            }

            if (! $agent->estUneFemme()) {
                $this->echouer('matricule', "{$agent->nomComplet()} n’est pas une agent de sexe féminin.");
            }

            $motif = $scrutin->phase->edition->motifIneligibilite($agent->matricule);
            if ($motif !== null) {
                $this->echouer('matricule', "{$agent->nomComplet()} est inéligible : {$motif}.");
            }

            if ($scrutin->candidates()->where('agent_id', $agent->id)->exists()) {
                $this->echouer('matricule', "{$agent->nomComplet()} est déjà candidate à ce scrutin.");
            }

            $candidate = $scrutin->candidates()->create([
                'agent_id' => $agent->id,
                'origine' => OrigineCandidate::Nomination,
                'reference_nomination' => mb_substr($reference, 0, 200),
                'nommee_par_id' => $auteur->id,
                'date_nomination' => now(),
            ]);
            $this->journal->handle(ActionAuditee::CandidateNominee, $candidate, ['scrutin' => $scrutin->code, 'matricule' => $agent->matricule, 'reference' => $candidate->reference_nomination], $auteur);

            return $candidate;
        });
    }

    /**
     * @throws ValidationException
     */
    private function echouer(string $champ, string $message): never
    {
        throw ValidationException::withMessages([$champ => $message]);
    }
}
