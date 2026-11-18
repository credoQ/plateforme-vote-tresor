<?php

namespace App\Enums\Acces;

/**
 * Rôles de la plateforme (R2).
 */
enum CodeRole: string
{
    case Administrateur = 'ADMINISTRATEUR';
    case Comite = 'COMITE';
    case PointFocal = 'POINT_FOCAL';
    case Electeur = 'ELECTEUR';
    case Observateur = 'OBSERVATEUR';

    public function libelle(): string
    {
        return match ($this) {
            self::Administrateur => 'Administrateur',
            self::Comite => 'Comité d’organisation',
            self::PointFocal => 'Point focal',
            self::Electeur => 'Électeur',
            self::Observateur => 'Observateur',
        };
    }
}
