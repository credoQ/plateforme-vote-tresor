<?php

namespace App\Actions\Resultats;

use App\Actions\Audit\Journaliser;
use App\Enums\Audit\ActionAuditee;
use App\Models\Candidate;
use App\Models\Scrutin;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Enregistre la décision motivée du comité qui départage des ex-æquo (R49).
 *
 * La modalité (second tour, autre critère) reste à préciser par le métier : la plateforme enregistre
 * seulement son issue, les lauréates retenues parmi les ex-æquo, et le motif porté au procès-verbal.
 */
class TrancherEgalite
{
    public function __construct(private Journaliser $journal) {}

    /**
     * @param  list<Candidate>  $retenues
     *
     * @throws ValidationException
     */
    public function handle(Scrutin $scrutin, array $retenues, string $motif): Scrutin
    {
        return DB::transaction(function () use ($scrutin, $retenues, $motif): Scrutin {
            $scrutin = Scrutin::with(['phase', 'procesVerbal'])->lockForUpdate()->findOrFail($scrutin->id);
            $pv = $scrutin->procesVerbal;

            if ($pv === null || ! $pv->egalite_a_departager || $pv->date_validation !== null) {
                $this->echouer('Ce scrutin n’a aucune égalité à départager.');
            }

            if (trim($motif) === '') {
                $this->echouer('Le départage doit être motivé (R49).');
            }

            $candidates = $scrutin->candidates()->actives()->get();
            $places = $scrutin->phase->nb_qualifiees - $candidates->where('est_laureate', true)->count();
            $rangEnBalance = $candidates->where('est_laureate', false)->min('rang');
            $exAequo = $candidates->where('rang', $rangEnBalance)->modelKeys();
            $retenuesIds = collect($retenues)->map->getKey()->unique()->values();

            if ($retenuesIds->count() !== $places || $retenuesIds->diff($exAequo)->isNotEmpty()) {
                $this->echouer("Le comité doit retenir exactement {$places} candidate(s) parmi les ex-æquo.");
            }

            $scrutin->candidates()->whereKey($retenuesIds)->update(['est_laureate' => true]);
            $pv->update(['egalite_a_departager' => false, 'motif_departage' => trim($motif)]);
            $this->journal->handle(ActionAuditee::EgaliteTranchee, $scrutin, ['scrutin' => $scrutin->code, 'retenues' => $retenuesIds->all(), 'motif' => trim($motif)]);

            return $scrutin;
        });
    }

    /**
     * @throws ValidationException
     */
    private function echouer(string $message): never
    {
        throw ValidationException::withMessages(['scrutin' => $message]);
    }
}
