<?php

namespace App\Models;

use App\Enums\Scrutins\StatutScrutin;
use Database\Factories\ScrutinFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * Circonscription du scrutin : les structures qu'il couvre (COUVRIR, R24).
     *
     * @return BelongsToMany<Structure, $this>
     */
    public function structures(): BelongsToMany
    {
        return $this->belongsToMany(Structure::class, 'couvertures')->withTimestamps();
    }

    /**
     * Liste électorale du scrutin (R24).
     *
     * @return HasMany<Inscription, $this>
     */
    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class);
    }

    /**
     * @return HasMany<Candidate, $this>
     */
    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    /**
     * Les listes électorales et les candidates ne changent plus une fois arrêtées (R27, R30) ;
     * seules la désactivation et la réactivation restent possibles jusqu'à l'ouverture (R33, R34).
     */
    public function listesSontModifiables(): bool
    {
        return $this->statut === StatutScrutin::EnPreparation;
    }

    /**
     * Participation, seule donnée visible pendant le vote (R46).
     *
     * @return array{inscrits: int, votants: int, taux: float}
     */
    public function participation(): array
    {
        $inscrits = $this->inscriptions()->count();
        $votants = $this->inscriptions()->where('a_vote', true)->count();

        return [
            'inscrits' => $inscrits,
            'votants' => $votants,
            'taux' => $inscrits === 0 ? 0.0 : round($votants * 100 / $inscrits, 2),
        ];
    }

    public function accepteLesDesistements(): bool
    {
        return in_array($this->statut, [StatutScrutin::EnPreparation, StatutScrutin::ListesArretees], true);
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
