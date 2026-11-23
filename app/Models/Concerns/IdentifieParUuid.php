<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Modèle dont la fiche s'ouvre par une adresse : la colonne uuid, générée à la création, remplace le numéro
 * de la ligne dans les adresses. La clé primaire reste l'identifiant numérique pour les relations.
 */
trait IdentifieParUuid
{
    use HasUuids;

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
