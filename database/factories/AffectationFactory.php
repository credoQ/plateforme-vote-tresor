<?php

namespace Database\Factories;

use App\Models\Affectation;
use App\Models\Agent;
use App\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Affectation>
 */
class AffectationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
            'structure_id' => Structure::factory(),
            'date_debut' => fake()->dateTimeBetween('-5 years', '-1 year')->format('Y-m-d'),
            'date_fin' => null,
        ];
    }

    public function terminee(string $dateFin): static
    {
        return $this->state(fn (): array => ['date_fin' => $dateFin]);
    }
}
