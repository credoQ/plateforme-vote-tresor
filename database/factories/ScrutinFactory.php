<?php

namespace Database\Factories;

use App\Enums\Scrutins\StatutScrutin;
use App\Models\Phase;
use App\Models\Scrutin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Scrutin>
 */
class ScrutinFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phase_id' => Phase::factory(),
            'code' => 'SCR-'.fake()->unique()->numerify('#####'),
            'libelle' => 'Scrutin '.fake()->unique()->word(),
            'statut' => StatutScrutin::EnPreparation,
        ];
    }

    public function statut(StatutScrutin $statut): static
    {
        return $this->state(fn (): array => ['statut' => $statut]);
    }
}
