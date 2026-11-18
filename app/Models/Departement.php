<?php

namespace App\Models;

use Database\Factories\DepartementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Département géographique (R9).
 */
#[Table('departements')]
#[Fillable(['code', 'libelle'])]
class Departement extends Model
{
    /** @use HasFactory<DepartementFactory> */
    use HasFactory;

    /**
     * @return HasMany<Structure, $this>
     */
    public function structures(): HasMany
    {
        return $this->hasMany(Structure::class);
    }
}
