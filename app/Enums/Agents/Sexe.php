<?php

namespace App\Enums\Agents;

/**
 * Sexe de l'agent (R11) : il détermine la qualité de candidate en phase 1.
 */
enum Sexe: string
{
    case Feminin = 'F';
    case Masculin = 'M';

    public function libelle(): string
    {
        return match ($this) {
            self::Feminin => 'Féminin',
            self::Masculin => 'Masculin',
        };
    }

    /**
     * Reconnaît les écritures usuelles des fichiers d'import (F, M, Féminin, Masculin, Femme, Homme).
     */
    public static function depuisSaisie(?string $valeur): ?self
    {
        $valeur = mb_strtoupper(trim((string) $valeur));

        return match (true) {
            $valeur === '' => null,
            in_array($valeur, ['F', 'FÉMININ', 'FEMININ', 'FEMME'], true) => self::Feminin,
            in_array($valeur, ['M', 'H', 'MASCULIN', 'HOMME'], true) => self::Masculin,
            default => null,
        };
    }
}
