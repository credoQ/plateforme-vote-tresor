<?php

namespace App\Actions\Vote;

use Carbon\CarbonImmutable;

/**
 * Accusé de vote remis à l'électeur : il prouve la participation sans rien dire du choix exprimé.
 */
final class AccuseDeVote
{
    public function __construct(
        public readonly string $numero,
        public readonly string $scrutin,
        public readonly CarbonImmutable $dateHeure,
    ) {}
}
