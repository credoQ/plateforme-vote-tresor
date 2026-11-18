<?php

namespace Database\Factories;

use App\Enums\Referentiel\CodeTypeStructure;
use App\Models\Departement;
use App\Models\Structure;
use App\Models\TypeStructure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Structure>
 */
class StructureFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'S-'.fake()->unique()->numerify('#####'),
            'sigle' => null,
            'libelle' => 'Structure '.fake()->unique()->word(),
            'niveau' => Structure::NIVEAU_STRUCTURE_ELECTORALE,
            'actif' => true,
            'type_structure_id' => TypeStructure::factory(),
        ];
    }

    /**
     * Structure d'un type de référence existant (le ReferentielSeeder doit avoir été exécuté).
     */
    public function deType(CodeTypeStructure $type): static
    {
        return $this->state(fn (): array => [
            'type_structure_id' => TypeStructure::where('code', $type->value)->value('id'),
        ]);
    }

    public function dansDepartement(Departement $departement): static
    {
        return $this->state(fn (): array => ['departement_id' => $departement->id]);
    }

    public function serviceDe(Structure $parente): static
    {
        return $this->state(fn (): array => [
            'niveau' => Structure::NIVEAU_SERVICE,
            'structure_parente_id' => $parente->id,
            'type_structure_id' => $parente->type_structure_id,
            'departement_id' => $parente->departement_id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['actif' => false]);
    }
}
