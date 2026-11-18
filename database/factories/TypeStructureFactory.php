<?php

namespace Database\Factories;

use App\Enums\Referentiel\CodeTypeStructure;
use App\Models\PoleElectoral;
use App\Models\TypeStructure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Les types de structure sont des données de référence : dans les tests, préférez le ReferentielSeeder.
 *
 * @extends Factory<TypeStructure>
 */
class TypeStructureFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => CodeTypeStructure::Cellule,
            'libelle' => CodeTypeStructure::Cellule->libelle(),
            'pole_electoral_id' => PoleElectoral::factory(),
        ];
    }
}
