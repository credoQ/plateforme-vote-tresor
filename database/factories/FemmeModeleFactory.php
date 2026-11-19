<?php

namespace Database\Factories;

use App\Enums\Editions\SourceFemmeModele;
use App\Models\FemmeModele;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FemmeModele>
 */
class FemmeModeleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'matricule' => fake()->unique()->numerify('######'),
            'nom_complet' => fake()->lastName().' '.fake()->firstNameFemale(),
            'annee' => 2024,
            'source' => SourceFemmeModele::Import,
        ];
    }
}
