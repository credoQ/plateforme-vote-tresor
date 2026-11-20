<?php

namespace App\Models;

use App\Enums\Referentiel\CodeTypeStructure;
use Database\Factories\TypeStructureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Type de structure (R7), classé dans un seul pôle électoral.
 */
#[Table('types_structure')]
#[Fillable(['code', 'libelle', 'pole_electoral_id'])]
class TypeStructure extends Model
{
    /** @use HasFactory<TypeStructureFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<PoleElectoral, $this>
     */
    public function poleElectoral(): BelongsTo
    {
        return $this->belongsTo(PoleElectoral::class);
    }

    /**
     * @return HasMany<Structure, $this>
     */
    public function structures(): HasMany
    {
        return $this->hasMany(Structure::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'code' => CodeTypeStructure::class,
        ];
    }
}
