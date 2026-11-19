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
 * Phase d'une édition (R17) : une édition en compte de une à trois, paramétrées à sa création.
 * Chaque phase déclare ses collèges (R20 à R23), qui portent le découpage, l'origine des candidates et le nombre d'élues.
 */
#[Table('phases')]
#[Fillable(['edition_id', 'numero', 'libelle', 'date_debut', 'date_fin', 'statut', 'date_ouverture', 'date_cloture'])]
class Phase extends Model
{
    /** @use HasFactory<PhaseFactory> */
    use HasFactory;

    /**
     * Numéros des phases de l'organisation par défaut (primaires, demi-finales, finale).
     */
    public const PRIMAIRES = 1;

    public const DEMI_FINALES = 2;

    public const FINALE = 3;

    public const NOMBRE_MAXIMUM = 3;

    /**
     * Clé du collège qui réunit toute la DGTCP, sans pôle ; les autres collèges sont désignés par le code de leur pôle.
     */
    public const COLLEGE_DGTCP = 'DGTCP';

    /**
     * Organisation proposée à la création d'une édition : trois phases, du découpage par circonscription
     * avec candidates d'office jusqu'à la finale de toute la DGTCP. Le comité l'ajuste avant de créer l'édition.
     *
     * @var array<int, array{libelle: string, colleges: array<string, array{decoupage: string, origine: string, nb_elues: int}>}>
     */
    public const ORGANISATION_PAR_DEFAUT = [
        self::PRIMAIRES => ['libelle' => 'Phase 1 · primaires', 'colleges' => [
            'DEPARTEMENTAL' => ['decoupage' => 'PAR_CIRCONSCRIPTION', 'origine' => 'AUTOMATIQUE', 'nb_elues' => 1],
            'STRUCTURES' => ['decoupage' => 'PAR_CIRCONSCRIPTION', 'origine' => 'AUTOMATIQUE', 'nb_elues' => 1],
        ]],
        self::DEMI_FINALES => ['libelle' => 'Phase 2 · demi-finales', 'colleges' => [
            'DEPARTEMENTAL' => ['decoupage' => 'ENSEMBLE', 'origine' => 'QUALIFICATION', 'nb_elues' => 1],
            'STRUCTURES' => ['decoupage' => 'ENSEMBLE', 'origine' => 'QUALIFICATION', 'nb_elues' => 1],
        ]],
        self::FINALE => ['libelle' => 'Phase 3 · finale', 'colleges' => [
            self::COLLEGE_DGTCP => ['decoupage' => 'ENSEMBLE', 'origine' => 'QUALIFICATION', 'nb_elues' => 1],
        ]],
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'statut' => 'PLANIFIEE',
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

    /**
     * @return HasMany<College, $this>
     */
    public function colleges(): HasMany
    {
        return $this->hasMany(College::class)->orderBy('pole_electoral_id');
    }

    public function phasePrecedente(): ?self
    {
        return $this->numero <= 1
            ? null
            : self::where('edition_id', $this->edition_id)->where('numero', $this->numero - 1)->first();
    }

    public function phaseSuivante(): ?self
    {
        return self::where('edition_id', $this->edition_id)->where('numero', $this->numero + 1)->first();
    }

    /**
     * Dernière phase de l'édition : ses lauréates sont les femmes modèles du Trésor (R53).
     */
    public function estLaDerniere(): bool
    {
        return ! self::where('edition_id', $this->edition_id)->where('numero', '>', $this->numero)->exists();
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
            'statut' => StatutPhase::class,
            'date_ouverture' => 'immutable_datetime',
            'date_cloture' => 'immutable_datetime',
        ];
    }
}
