<?php

namespace App\Enums\Editions;

/**
 * Statut d'une édition (R16) : une seule édition peut être en cours.
 */
enum StatutEdition: string
{
    case EnPreparation = 'EN_PREPARATION';
    case EnCours = 'EN_COURS';
    case Cloturee = 'CLOTUREE';

    public function libelle(): string
    {
        return match ($this) {
            self::EnPreparation => 'En préparation',
            self::EnCours => 'En cours',
            self::Cloturee => 'Clôturée',
        };
    }
}
