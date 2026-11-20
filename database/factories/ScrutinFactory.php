<?php

namespace Database\Factories;

use App\Enums\Scrutins\StatutScrutin;
use App\Models\College;
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
            // Le collège de la phase qui correspond au pôle du scrutin, sinon le premier, sinon un collège de toute la DGTCP.
            'college_id' => fn (array $attributs): int => College::where('phase_id', $attributs['phase_id'])
                ->orderByRaw('pole_electoral_id IS DISTINCT FROM ?', [$attributs['pole_electoral_id'] ?? null])
                ->value('id') ?? College::factory()->create(['phase_id' => $attributs['phase_id']])->id,
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
