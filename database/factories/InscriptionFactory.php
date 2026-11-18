<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Inscription;
use App\Models\Scrutin;
use App\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inscription>
 */
class InscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scrutin_id' => Scrutin::factory(),
            'phase_id' => fn (array $attributs): int => Scrutin::findOrFail($attributs['scrutin_id'])->phase_id,
            'agent_id' => Agent::factory(),
            'structure_id' => Structure::factory(),
        ];
    }
}
