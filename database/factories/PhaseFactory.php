<?php

namespace Database\Factories;

use App\Models\Edition;
use App\Models\Phase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Phase>
 */
class PhaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'edition_id' => Edition::factory(),
            'numero' => Phase::PRIMAIRES,
            'libelle' => Phase::LIBELLES[Phase::PRIMAIRES],
        ];
    }
}
