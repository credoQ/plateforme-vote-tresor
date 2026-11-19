<?php

namespace App\Enums\Editions;

/**
 * Origine d'une ligne de l'historique des femmes modèles (R59) : proclamée par la plateforme, ou importée
 * pour une édition antérieure.
 */
enum SourceFemmeModele: string
{
    case Plateforme = 'PLATEFORME';
    case Import = 'IMPORT';

    public function libelle(): string
    {
        return match ($this) {
            self::Plateforme => 'Proclamée sur la plateforme',
            self::Import => 'Importée',
        };
    }
}
