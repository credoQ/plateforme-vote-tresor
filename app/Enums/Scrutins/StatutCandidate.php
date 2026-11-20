<?php

namespace App\Enums\Scrutins;

/**
 * Statut d'une candidate (R31 à R34).
 */
enum StatutCandidate: string
{
    case Active = 'ACTIVE';
    case Desactivee = 'DESACTIVEE';

    public function libelle(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Desactivee => 'Désactivée',
        };
    }
}
