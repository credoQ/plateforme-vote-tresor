<?php

namespace App\Enums\Scrutins;

/**
 * Cycle de vie d'un scrutin (R19, R27, R38, R51).
 */
enum StatutScrutin: string
{
    case EnPreparation = 'EN_PREPARATION';
    case ListesArretees = 'LISTES_ARRETEES';
    case Ouvert = 'OUVERT';
    case Clos = 'CLOS';
    case ResultatsValides = 'RESULTATS_VALIDES';
    case ResultatsPublies = 'RESULTATS_PUBLIES';
    case Annule = 'ANNULE';

    public function libelle(): string
    {
        return match ($this) {
            self::EnPreparation => 'En préparation',
            self::ListesArretees => 'Listes arrêtées',
            self::Ouvert => 'Ouvert',
            self::Clos => 'Clos',
            self::ResultatsValides => 'Résultats validés',
            self::ResultatsPublies => 'Résultats publiés',
            self::Annule => 'Annulé',
        };
    }

    /**
     * Le scrutin n'attend plus rien pour la suite de l'édition : résultats publiés, ou annulé faute de candidate (R18, R38).
     */
    public function estTermine(): bool
    {
        return $this === self::ResultatsPublies || $this === self::Annule;
    }
}
