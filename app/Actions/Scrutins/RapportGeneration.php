<?php

namespace App\Actions\Scrutins;

use App\Models\Scrutin;
use Illuminate\Support\Collection;

/**
 * Bilan de la génération des scrutins d'une phase.
 */
final class RapportGeneration
{
    /**
     * @param  Collection<int, Scrutin>  $scrutins  scrutins créés
     * @param  list<string>  $circonscriptionsSansFemme  circonscriptions écartées, à signaler au comité (R37)
     * @param  list<string>  $circonscriptionsSansAgent  circonscriptions sans aucun agent en service, écartées et signalées
     */
    public function __construct(
        public readonly Collection $scrutins,
        public readonly array $circonscriptionsSansFemme,
        public readonly array $circonscriptionsSansAgent = [],
    ) {}
}
