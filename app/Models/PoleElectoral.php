<?php

namespace App\Models;

use App\Enums\Referentiel\CodePoleElectoral;
use Database\Factories\PoleElectoralFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pôle électoral (R6) : départemental ou structures.
 */
#[Table('poles_electoraux')]
#[Fillable(['code', 'libelle'])]
class PoleElectoral extends Model
{
    /** @use HasFactory<PoleElectoralFactory> */
    use HasFactory;

    /**
     * @return HasMany<TypeStructure, $this>
     */
    public function typesStructure(): HasMany
    {
        return $this->hasMany(TypeStructure::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'code' => CodePoleElectoral::class,
        ];
    }
}
