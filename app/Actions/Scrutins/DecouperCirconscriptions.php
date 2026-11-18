<?php

namespace App\Actions\Scrutins;

use App\Enums\Referentiel\CodePoleElectoral;
use App\Models\Phase;
use App\Models\Structure;
use Illuminate\Support\Collection;

/**
 * Découpe les structures actives en circonscriptions pour une phase (R20 à R25).
 *
 * - Une structure située dans un département (TD, Trésorerie communale, antenne d'une direction centrale
 *   en poste dans une TD) vote avec ce département (R25).
 * - Les autres votent avec leur structure électorale : elles-mêmes au niveau 1, leur structure mère pour un service (R10, R24).
 *
 * Phase 1 : un scrutin par département et un par structure électorale ; phase 2 : un par pôle ; phase 3 : la finale.
 * Les circonscriptions d'une même phase sont disjointes, ce qui garantit qu'un agent n'est inscrit qu'une fois (R26).
 */
class DecouperCirconscriptions
{
    /**
     * @return Collection<string, array{code: string, libelle: string, pole: CodePoleElectoral|null, structures: list<int>}>
     */
    public function handle(Phase $phase): Collection
    {
        $structures = Structure::actives()
            ->with(['departement', 'typeStructure.poleElectoral', 'structureParente.typeStructure.poleElectoral'])
            ->orderBy('code')
            ->get();

        $circonscriptions = [];

        foreach ($structures as $structure) {
            $circonscription = $this->circonscription($phase->numero, $structure);
            if ($circonscription === null) {
                continue;
            }

            $circonscriptions[$circonscription['code']] ??= [...$circonscription, 'structures' => []];
            $circonscriptions[$circonscription['code']]['structures'][] = $structure->id;
        }

        return collect($circonscriptions)->sortBy(fn (array $c): string => ($c['pole'] === CodePoleElectoral::Departemental ? '0' : '1').$c['code']);
    }

    /**
     * @return array{code: string, libelle: string, pole: CodePoleElectoral|null}|null
     */
    private function circonscription(int $numeroPhase, Structure $structure): ?array
    {
        if ($numeroPhase === Phase::FINALE) {
            return ['code' => 'FINALE', 'libelle' => 'Finale', 'pole' => null];
        }

        if ($structure->departement !== null) {
            return $numeroPhase === Phase::PRIMAIRES
                ? ['code' => 'DEP-'.$structure->departement->code, 'libelle' => 'Scrutin départemental · '.$structure->departement->libelle, 'pole' => CodePoleElectoral::Departemental]
                : ['code' => 'DEMI-DEPARTEMENTAL', 'libelle' => 'Demi-finale départementale', 'pole' => CodePoleElectoral::Departemental];
        }

        $electorale = $structure->niveau === Structure::NIVEAU_STRUCTURE_ELECTORALE ? $structure : $structure->structureParente;
        if ($electorale === null || ! $electorale->actif) {
            return null;
        }

        $pole = $electorale->typeStructure->poleElectoral->code;

        if ($numeroPhase === Phase::PRIMAIRES) {
            return ['code' => 'STR-'.$electorale->code, 'libelle' => 'Scrutin de structure · '.$electorale->libelle, 'pole' => $pole];
        }

        return $pole === CodePoleElectoral::Departemental
            ? ['code' => 'DEMI-DEPARTEMENTAL', 'libelle' => 'Demi-finale départementale', 'pole' => $pole]
            : ['code' => 'DEMI-STRUCTURES', 'libelle' => 'Demi-finale des structures', 'pole' => $pole];
    }
}
