<?php

namespace App\Enums\Referentiel;

/**
 * Codes des pôles électoraux (R6).
 */
enum CodePoleElectoral: string
{
    case Departemental = 'DEPARTEMENTAL';
    case Structures = 'STRUCTURES';

    public function libelle(): string
    {
        return match ($this) {
            self::Departemental => 'Pôle départemental',
            self::Structures => 'Pôle structures',
        };
    }
}
