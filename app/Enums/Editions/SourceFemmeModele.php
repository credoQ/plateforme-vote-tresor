<?php

namespace App\Enums\Editions;

/**
 * Origine d'une ligne de l'historique des femmes modèles (R59) : proclamée par la plateforme, saisie par le comité
 * pour une édition antérieure, ou importée (lignes reprises avant la saisie unitaire).
 */
enum SourceFemmeModele: string
{
    case Plateforme = 'PLATEFORME';
    case Saisie = 'SAISIE';
    case Import = 'IMPORT';

    public function libelle(): string
    {
        return match ($this) {
            self::Plateforme => 'Proclamée sur la plateforme',
            self::Saisie => 'Saisie par le comité',
            self::Import => 'Importée',
        };
    }

    /**
     * Une ligne saisie ou importée peut être retirée de l'historique ; une proclamation de la plateforme, jamais.
     */
    public function estRetirable(): bool
    {
        return $this !== self::Plateforme;
    }
}
