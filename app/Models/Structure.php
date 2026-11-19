<?php

namespace App\Models;

use App\Enums\Referentiel\CodePoleElectoral;
use App\Enums\Referentiel\CodeTypeStructure;
use Database\Factories\StructureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Structure de la DGTCP (R5). Niveau 1 : structure électorale autonome (R10) ; niveau 2 : service rattaché.
 */
#[Table('structures')]
#[Fillable(['code', 'sigle', 'libelle', 'niveau', 'actif', 'type_structure_id', 'departement_id', 'structure_parente_id'])]
class Structure extends Model
{
    /** @use HasFactory<StructureFactory> */
    use HasFactory;

    public const NIVEAU_STRUCTURE_ELECTORALE = 1;

    public const NIVEAU_SERVICE = 2;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'niveau' => self::NIVEAU_STRUCTURE_ELECTORALE,
        'actif' => true,
    ];

    /**
     * @return BelongsTo<TypeStructure, $this>
     */
    public function typeStructure(): BelongsTo
    {
        return $this->belongsTo(TypeStructure::class);
    }

    /**
     * @return BelongsTo<Departement, $this>
     */
    public function departement(): BelongsTo
    {
        return $this->belongsTo(Departement::class);
    }

    /**
     * Structure mère (R8) : la direction d'un service, la TD d'une Trésorerie communale.
     *
     * @return BelongsTo<Structure, $this>
     */
    public function structureParente(): BelongsTo
    {
        return $this->belongsTo(self::class, 'structure_parente_id');
    }

    /**
     * @return HasMany<Structure, $this>
     */
    public function structuresFilles(): HasMany
    {
        return $this->hasMany(self::class, 'structure_parente_id');
    }

    /**
     * Affectations des agents à la structure, historique compris (R12).
     *
     * @return HasMany<Affectation, $this>
     */
    public function affectations(): HasMany
    {
        return $this->hasMany(Affectation::class);
    }

    /**
     * Service rattaché (niveau 2) : il vote avec sa structure mère (R10, R24).
     */
    public function estUnService(): bool
    {
        return $this->niveau === self::NIVEAU_SERVICE;
    }

    /**
     * Structures actives.
     */
    #[Scope]
    protected function actives(Builder $query): Builder
    {
        return $query->where('actif', true);
    }

    /**
     * Structures électorales autonomes (R10) : niveau 1, actives.
     */
    #[Scope]
    protected function electorales(Builder $query): Builder
    {
        return $query->actives()->where('niveau', self::NIVEAU_STRUCTURE_ELECTORALE);
    }

    /**
     * Structures du type donné (R5).
     */
    #[Scope]
    protected function duType(Builder $query, CodeTypeStructure $type): Builder
    {
        return $query->whereHas('typeStructure', fn (Builder $q) => $q->where('code', $type->value));
    }

    /**
     * Structures dont le type est classé dans le pôle donné (R7).
     */
    #[Scope]
    protected function duPole(Builder $query, CodePoleElectoral $pole): Builder
    {
        return $query->whereHas('typeStructure.poleElectoral', fn (Builder $q) => $q->where('code', $pole->value));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'niveau' => 'integer',
            'actif' => 'boolean',
        ];
    }
}
