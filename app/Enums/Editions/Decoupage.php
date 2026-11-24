<?php

namespace App\Enums\Editions;

/**
 * Découpage d'un collège en scrutins (R20) : un scrutin par circonscription (département, structure électorale)
 * ou un seul scrutin pour tout le collège (pôle électoral, ou ensemble de la DGTCP).
 */
enum Decoupage: string
{
    case ParCirconscription = 'PAR_CIRCONSCRIPTION';
    case Ensemble = 'ENSEMBLE';

    public function libelle(): string
    {
        return match ($this) {
            self::ParCirconscription => 'Un scrutin par département ou par structure',
            self::Ensemble => 'Un seul scrutin pour tout le périmètre',
        };
    }
}
