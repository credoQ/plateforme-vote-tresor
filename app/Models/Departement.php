<?php

namespace App\Models;

use App\Enums\Referentiel\CodeTypeStructure;
use Database\Factories\DepartementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    /**
     * Trésorerie départementale active du département (R9).
     *
     * @return HasOne<Structure, $this>
     */
    public function tresorerieDepartementale(): HasOne
    {
        return $this->hasOne(Structure::class)->actives()->duType(CodeTypeStructure::TresorerieDepartementale);
    }

    /**
     * Trésoreries communales actives du département (R9).
     *
     * @return HasMany<Structure, $this>
     */
    public function tresoreriesCommunales(): HasMany
    {
        return $this->hasMany(Structure::class)->actives()->duType(CodeTypeStructure::TresorerieCommunale);
    }
}
