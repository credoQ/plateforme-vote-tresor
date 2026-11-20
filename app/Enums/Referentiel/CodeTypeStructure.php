<?php

namespace App\Enums\Referentiel;

/**
 * Codes des types de structure (R7) et pôle électoral de chacun.
 */
enum CodeTypeStructure: string
{
    case Cellule = 'CELLULE';
    case DirectionCentrale = 'DIRECTION_CENTRALE';
    case Tmie = 'TMIE';
    case Acd = 'ACD';
    case TresorerieDepartementale = 'TD';
    case TresorerieCommunale = 'TC';

    public function libelle(): string
    {
        return match ($this) {
            self::Cellule => 'Cellule',
            self::DirectionCentrale => 'Direction centrale',
            self::Tmie => "Trésorerie de Ministère ou d'Institution de l'État",
            self::Acd => 'Agence comptable de la Dette',
            self::TresorerieDepartementale => 'Trésorerie départementale',
            self::TresorerieCommunale => 'Trésorerie communale',
        };
    }

    public function pole(): CodePoleElectoral
    {
        return match ($this) {
            self::TresorerieDepartementale, self::TresorerieCommunale => CodePoleElectoral::Departemental,
            default => CodePoleElectoral::Structures,
        };
    }

    /**
     * Les postes déconcentrés doivent être situés dans un département (R9).
     */
    public function exigeUnDepartement(): bool
    {
        return $this->pole() === CodePoleElectoral::Departemental;
    }
}
