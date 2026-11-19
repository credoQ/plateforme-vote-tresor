<?php

namespace App\Models;

use App\Enums\Editions\StatutPhase;
use Database\Factories\PhaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Phase d'une édition (R17) : 1 primaires, 2 demi-finales, 3 finale.
 * Le nombre de qualifiées par scrutin est un paramètre, une par défaut (R52).
 */
#[Table('phases')]
#[Fillable(['edition_id', 'numero', 'libelle', 'date_debut', 'date_fin', 'nb_qualifiees', 'statut', 'date_ouverture', 'date_cloture'])]
class Phase extends Model
{
    /** @use HasFactory<PhaseFactory> */
    use HasFactory;

    public const PRIMAIRES = 1;

    public const DEMI_FINALES = 2;

    public const FINALE = 3;

    /**
     * Libellés des trois phases (R17).
     *
     * @var array<int, string>
     */
    public const LIBELLES = [
        self::PRIMAIRES => 'Phase 1 · primaires',
        self::DEMI_FINALES => 'Phase 2 · demi-finales',
        self::FINALE => 'Phase 3 · finale',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'statut' => 'PLANIFIEE',
        'nb_qualifiees' => 1,
    ];

    /**
     * @return BelongsTo<Edition, $this>
     */
    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    /**
     * @return HasMany<Scrutin, $this>
     */
    public function scrutins(): HasMany
    {
        return $this->hasMany(Scrutin::class);
    }

    public function phasePrecedente(): ?self
    {
        return $this->numero === self::PRIMAIRES
            ? null
            : self::where('edition_id', $this->edition_id)->where('numero', $this->numero - 1)->first();
    }

    public function phaseSuivante(): ?self
    {
        return $this->estLaFinale()
            ? null
            : self::where('edition_id', $this->edition_id)->where('numero', $this->numero + 1)->first();
    }

    public function estLaFinale(): bool
    {
        return $this->numero === self::FINALE;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'date_debut' => 'date',
            'date_fin' => 'date',
            'nb_qualifiees' => 'integer',
            'statut' => StatutPhase::class,
            'date_ouverture' => 'immutable_datetime',
            'date_cloture' => 'immutable_datetime',
        ];
    }
}
