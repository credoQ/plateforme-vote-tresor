<?php

namespace App\Enums\Scrutins;

/**
 * Origine des candidates d'un collège, et de chaque candidate (R28) : d'office (toutes les femmes en service),
 * par nomination du comité, ou par qualification depuis la phase précédente (R35).
 */
enum OrigineCandidate: string
{
    case Automatique = 'AUTOMATIQUE';
    case Nomination = 'NOMINATION';
    case Qualification = 'QUALIFICATION';

    public function libelle(): string
    {
        return match ($this) {
            self::Automatique => 'Candidate d’office',
            self::Nomination => 'Nominée par le comité',
            self::Qualification => 'Lauréate qualifiée',
        };
    }

    /**
     * Libellé du réglage d'un collège.
     */
    public function libelleCollege(): string
    {
        return match ($this) {
            self::Automatique => 'D’office : toutes les femmes en service',
            self::Nomination => 'Nomination par le comité',
            self::Qualification => 'Lauréates de la phase précédente',
        };
    }
}
