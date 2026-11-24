<?php

namespace App\Actions\Scrutins;

use App\Actions\Audit\Journaliser;
use App\Enums\Audit\ActionAuditee;
use App\Enums\Editions\StatutEdition;
use App\Enums\Editions\StatutPhase;
use App\Enums\Scrutins\OrigineCandidate;
use App\Models\Affectation;
use App\Models\Agent;
use App\Models\Phase;
use App\Models\PoleElectoral;
use App\Models\Scrutin;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Génère les scrutins d'une phase et leur circonscription (R19 à R24), puis constitue leurs listes (R28, R35).
 *
 * Pour un collège aux candidates d'office, une circonscription sans agent femme en service à la date de gel
 * n'a pas de scrutin : elle est signalée au comité et ses agents ne votent pas dans cette phase (R20, R37).
 * Une circonscription sans aucun agent en service n'a pas de scrutin non plus, quel que soit le collège : sans
 * électeur, elle bloquerait l'arrêt des listes de la phase (R27). Elle est signalée de même.
 */
class GenererScrutins
{
    public function __construct(
        private DecouperCirconscriptions $decouper,
        private ConstituerListes $constituerListes,
        private Journaliser $journal,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(Phase $phase): RapportGeneration
    {
        $phase->loadMissing('edition');

        if ($phase->edition->statut === StatutEdition::Cloturee || $phase->statut !== StatutPhase::Planifiee) {
            $this->echouer('Les scrutins ne se génèrent que pour une phase planifiée d’une édition non clôturée.');
        }

        if ($phase->scrutins()->exists()) {
            $this->echouer("Les scrutins de la {$phase->libelle} ont déjà été générés.");
        }

        return DB::transaction(function () use ($phase): RapportGeneration {
            $gel = $phase->edition->dateDeReference();
            $poles = PoleElectoral::all()->keyBy(fn (PoleElectoral $pole): string => $pole->code->value);
            $scrutins = collect();
            $sansFemme = [];
            $sansAgent = [];

            foreach ($this->decouper->handle($phase) as $circonscription) {
                if (! $this->compteUnAgentEnService($circonscription['structures'], $gel)) {
                    $sansAgent[] = $circonscription['libelle'];

                    continue;
                }

                if ($circonscription['college']->origine_candidates === OrigineCandidate::Automatique && ! $this->compteUneFemmeEnService($circonscription['structures'], $gel)) {
                    $sansFemme[] = $circonscription['libelle'];

                    continue;
                }

                $scrutin = $phase->scrutins()->create([
                    'college_id' => $circonscription['college']->id,
                    'code' => $circonscription['code'],
                    'libelle' => $circonscription['libelle'],
                    'pole_electoral_id' => $circonscription['pole'] !== null ? $poles[$circonscription['pole']->value]->id : null,
                    'quorum' => $phase->edition->quorum,
                ]);
                $scrutin->structures()->attach($circonscription['structures']);

                $scrutins->push($this->constituerListes->handle($scrutin));
            }

            $this->journal->handle(ActionAuditee::ScrutinsGeneres, $phase, ['phase' => $phase->libelle, 'scrutins' => $scrutins->count(), 'sans_femme' => $sansFemme, 'sans_agent' => $sansAgent]);

            return new RapportGeneration($scrutins, $sansFemme, $sansAgent);
        });
    }

    /**
     * @param  list<int>  $structureIds
     */
    private function compteUnAgentEnService(array $structureIds, CarbonInterface $gel): bool
    {
        return Affectation::enCoursLe($gel)->whereIn('structure_id', $structureIds)->exists();
    }

    /**
     * @param  list<int>  $structureIds
     */
    private function compteUneFemmeEnService(array $structureIds, CarbonInterface $gel): bool
    {
        return Agent::femmes()
            ->whereIn('id', Affectation::enCoursLe($gel)->whereIn('structure_id', $structureIds)->select('agent_id'))
            ->exists();
    }

    /**
     * @throws ValidationException
     */
    private function echouer(string $message): never
    {
        throw ValidationException::withMessages(['phase' => $message]);
    }
}
