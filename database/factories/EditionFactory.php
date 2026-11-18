<?php

namespace Database\Factories;

use App\Enums\Editions\StatutEdition;
use App\Models\Edition;
use App\Models\Phase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Edition>
 */
class EditionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $annee = fake()->unique()->numberBetween(2030, 2099);

        return [
            'annee' => $annee,
            'libelle' => "Femme modèle du Trésor {$annee}",
            'date_debut' => "{$annee}-03-01",
            'date_fin' => "{$annee}-03-31",
            'date_gel' => "{$annee}-02-15",
            'statut' => StatutEdition::EnPreparation,
        ];
    }

    /**
     * Édition munie de ses trois phases (R17).
     */
    public function avecPhases(): static
    {
        return $this->afterCreating(function (Edition $edition): void {
            foreach (Phase::LIBELLES as $numero => $libelle) {
                $edition->phases()->create(['numero' => $numero, 'libelle' => $libelle]);
            }
        });
    }

    public function enCours(): static
    {
        return $this->state(fn (): array => ['statut' => StatutEdition::EnCours]);
    }
}
