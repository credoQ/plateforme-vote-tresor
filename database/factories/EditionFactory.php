<?php

namespace Database\Factories;

use App\Enums\Editions\StatutEdition;
use App\Models\Edition;
use App\Models\Phase;
use App\Models\PoleElectoral;
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
     * Édition munie des phases et des collèges de l'organisation par défaut (R17), ou de ceux donnés.
     *
     * @param  array<int, array{libelle: string, colleges: array<string, array{decoupage: string, origine: string, nb_elues: int}>}>|null  $organisation
     */
    public function avecPhases(?array $organisation = null): static
    {
        return $this->afterCreating(function (Edition $edition) use ($organisation): void {
            $poles = PoleElectoral::all()->keyBy(fn (PoleElectoral $pole): string => $pole->code->value);

            foreach ($organisation ?? Phase::ORGANISATION_PAR_DEFAUT as $numero => $phase) {
                $creee = $edition->phases()->create(['numero' => $numero, 'libelle' => $phase['libelle']]);

                foreach ($phase['colleges'] as $cle => $college) {
                    $creee->colleges()->create([
                        'pole_electoral_id' => $cle === Phase::COLLEGE_DGTCP ? null : $poles[$cle]->id,
                        'decoupage' => $college['decoupage'],
                        'origine_candidates' => $college['origine'],
                        'nb_elues' => $college['nb_elues'],
                    ]);
                }
            }
        });
    }

    public function enCours(): static
    {
        return $this->state(fn (): array => ['statut' => StatutEdition::EnCours]);
    }
}
