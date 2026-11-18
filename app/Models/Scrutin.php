<?php

namespace App\Models;

use App\Enums\Scrutins\StatutScrutin;
use Database\Factories\ScrutinFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scrutin d'une phase (R19). Il relève d'au plus un pôle électoral : la finale n'en a pas (R22).
 */
#[Table('scrutins')]
#[Fillable(['phase_id', 'pole_electoral_id', 'code', 'libelle', 'date_heure_ouverture', 'date_heure_fermeture', 'quorum', 'statut'])]
class Scrutin extends Model
{
    /** @use HasFactory<ScrutinFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'statut' => 'EN_PREPARATION',
    ];

    /**
     * @return BelongsTo<Phase, $this>
     */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    /**
     * @return BelongsTo<PoleElectoral, $this>
     */
    public function poleElectoral(): BelongsTo
    {
        return $this->belongsTo(PoleElectoral::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_heure_ouverture' => 'immutable_datetime',
            'date_heure_fermeture' => 'immutable_datetime',
            'quorum' => 'decimal:2',
            'statut' => StatutScrutin::class,
        ];
    }
}
