<?php

namespace Database\Factories;

use App\Enums\Referentiel\CodePoleElectoral;
use App\Models\PoleElectoral;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PoleElectoral>
 */
class PoleElectoralFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => CodePoleElectoral::Structures,
            'libelle' => CodePoleElectoral::Structures->libelle(),
        ];
    }

    public function departemental(): static
    {
        return $this->state(fn (): array => [
            'code' => CodePoleElectoral::Departemental,
            'libelle' => CodePoleElectoral::Departemental->libelle(),
        ]);
    }
}
