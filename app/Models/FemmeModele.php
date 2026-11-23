<?php

namespace App\Models;

use App\Enums\Editions\SourceFemmeModele;
use App\Models\Concerns\IdentifieParUuid;
use Database\Factories\FemmeModeleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Femme modèle du Trésor d'une année (R59) : historique qui fonde l'inéligibilité. L'agent est repérée par son
 * matricule ; le lien vers sa fiche existe quand elle figure dans la liste des agents.
 */
#[Table('femmes_modeles')]
#[Fillable(['matricule', 'agent_id', 'nom_complet', 'annee', 'edition_id', 'scrutin', 'source'])]
class FemmeModele extends Model
{
    /** @use HasFactory<FemmeModeleFactory> */
    use HasFactory, IdentifieParUuid;

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * @return BelongsTo<Edition, $this>
     */
    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'annee' => 'integer',
            'source' => SourceFemmeModele::class,
        ];
    }
}
