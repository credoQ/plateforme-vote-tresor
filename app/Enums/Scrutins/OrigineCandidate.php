<?php

namespace App\Enums\Scrutins;

/**
 * Origine d'une candidate : d'office en phase 1 (R28), par qualification en phases 2 et 3 (R35).
 */
enum OrigineCandidate: string
{
    case Automatique = 'AUTOMATIQUE';
    case Qualification = 'QUALIFICATION';

    public function libelle(): string
    {
        return match ($this) {
            self::Automatique => 'Candidate d’office',
            self::Qualification => 'Lauréate qualifiée',
        };
    }
}
