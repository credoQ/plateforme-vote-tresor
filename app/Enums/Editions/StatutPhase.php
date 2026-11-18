<?php

namespace App\Enums\Editions;

/**
 * Statut d'une phase (R17, R18) : planifiée, ouverte puis clôturée, dans l'ordre des phases.
 */
enum StatutPhase: string
{
    case Planifiee = 'PLANIFIEE';
    case Ouverte = 'OUVERTE';
    case Cloturee = 'CLOTUREE';

    public function libelle(): string
    {
        return match ($this) {
            self::Planifiee => 'Planifiée',
            self::Ouverte => 'Ouverte',
            self::Cloturee => 'Clôturée',
        };
    }
}
