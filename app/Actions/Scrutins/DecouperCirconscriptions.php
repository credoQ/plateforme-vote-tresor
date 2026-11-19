<?php

namespace App\Actions\Scrutins;

use App\Enums\Editions\Decoupage;
use App\Enums\Referentiel\CodePoleElectoral;
use App\Models\College;
use App\Models\Phase;
use App\Models\Structure;
use Illuminate\Support\Collection;

/**
 * Découpe les structures actives en circonscriptions pour une phase, selon ses collèges (R20 à R25).
 *
 * - Une structure située dans un département (TD, Trésorerie communale, antenne d'une direction centrale
 *   en poste dans une TD) relève du pôle départemental et vote avec ce département (R25).
 * - Les autres relèvent du pôle de leur structure électorale : elles-mêmes au niveau 1, leur structure mère
 *   pour un service (R10, R24).
 * - Un collège découpé par circonscription donne un scrutin par département ou par structure électorale ;
 *   un collège d'un seul tenant donne un scrutin pour tout son pôle, ou pour toute la DGTCP.
 * - Une structure dont le pôle n'a pas de collège dans la phase ne vote pas dans cette phase.
 *
 * Les circonscriptions d'une même phase sont disjointes, ce qui garantit qu'un agent n'est inscrit qu'une fois (R26).
 */
class DecouperCirconscriptions
{
    /**
     * @return Collection<string, array{code: string, libelle: string, pole: CodePoleElectoral|null, college: College, structures: list<int>}>
     */
    public function handle(Phase $phase): Collection
    {
        $colleges = $phase->colleges()->with('poleElectoral')->get();
        $dgtcp = $colleges->firstWhere('pole_electoral_id', null);
        $parPole = $colleges->whereNotNull('pole_electoral_id')->keyBy(fn (College $college): string => $college->poleElectoral->code->value);

        $structures = Structure::actives()
            ->with(['departement', 'typeStructure.poleElectoral', 'structureParente.typeStructure.poleElectoral'])
            ->orderBy('code')
            ->get();

        $circonscriptions = [];

        foreach ($structures as $structure) {
            $circonscription = $dgtcp !== null
                ? ['code' => 'DGTCP', 'libelle' => 'Scrutin de l’ensemble de la DGTCP', 'pole' => null, 'college' => $dgtcp]
                : $this->circonscription($structure, $parPole);

            if ($circonscription === null) {
                continue;
            }

            $circonscriptions[$circonscription['code']] ??= [...$circonscription, 'structures' => []];
            $circonscriptions[$circonscription['code']]['structures'][] = $structure->id;
        }

        return collect($circonscriptions)->sortBy(fn (array $c): string => ($c['pole'] === CodePoleElectoral::Departemental ? '0' : '1').$c['code']);
    }

    /**
     * @param  Collection<string, College>  $parPole
     * @return array{code: string, libelle: string, pole: CodePoleElectoral, college: College}|null
     */
    private function circonscription(Structure $structure, Collection $parPole): ?array
    {
        $electorale = $structure->niveau === Structure::NIVEAU_STRUCTURE_ELECTORALE ? $structure : $structure->structureParente;

        if ($structure->departement !== null) {
            $pole = CodePoleElectoral::Departemental;
        } elseif ($electorale !== null && $electorale->actif) {
            $pole = $electorale->typeStructure->poleElectoral->code;
        } else {
            return null;
        }

        $college = $parPole->get($pole->value);
        if ($college === null) {
            return null;
        }

        if ($college->decoupage === Decoupage::Ensemble) {
            return ['code' => 'POLE-'.$pole->value, 'libelle' => 'Scrutin du '.mb_strtolower($pole->libelle()), 'pole' => $pole, 'college' => $college];
        }

        return $structure->departement !== null
            ? ['code' => 'DEP-'.$structure->departement->code, 'libelle' => 'Scrutin départemental · '.$structure->departement->libelle, 'pole' => $pole, 'college' => $college]
            : ['code' => 'STR-'.$electorale->code, 'libelle' => 'Scrutin de structure · '.$electorale->libelle, 'pole' => $pole, 'college' => $college];
    }
}
