<?php

namespace Database\Factories;

use App\Enums\Agents\Sexe;
use App\Models\Affectation;
use App\Models\Agent;
use App\Models\Structure;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agent>
 */
class AgentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sexe = fake()->randomElement(Sexe::cases());

        return [
            'matricule' => fake()->unique()->numerify('######'),
            'npi' => fake()->unique()->numerify('##########'),
            'nom' => mb_strtoupper(fake()->lastName()),
            'prenoms' => fake()->firstName($sexe === Sexe::Feminin ? 'female' : 'male'),
            'sexe' => $sexe,
            'date_naissance' => fake()->dateTimeBetween('-60 years', '-22 years')->format('Y-m-d'),
        ];
    }

    public function femme(): static
    {
        return $this->state(fn (): array => [
            'sexe' => Sexe::Feminin,
            'prenoms' => fake()->firstName('female'),
        ]);
    }

    public function homme(): static
    {
        return $this->state(fn (): array => [
            'sexe' => Sexe::Masculin,
            'prenoms' => fake()->firstName('male'),
        ]);
    }

    /**
     * Agent affecté à la structure donnée, affectation en cours depuis la date indiquée.
     */
    public function affecteA(Structure $structure, ?CarbonInterface $depuis = null): static
    {
        return $this->has(
            Affectation::factory()->state([
                'structure_id' => $structure->id,
                'date_debut' => ($depuis ?? now()->subYear())->toDateString(),
                'date_fin' => null,
            ]),
            'affectations',
        );
    }
}
