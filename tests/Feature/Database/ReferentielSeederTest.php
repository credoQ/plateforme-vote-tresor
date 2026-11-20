<?php

namespace Tests\Feature\Database;

use App\Enums\Referentiel\CodePoleElectoral;
use App\Enums\Referentiel\CodeTypeStructure;
use App\Models\TypeStructure;
use Database\Seeders\ReferentielSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferentielSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * R6, R7 : deux pôles, six types, chacun dans le pôle attendu.
     */
    public function test_r7_classe_chaque_type_de_structure_dans_son_pole(): void
    {
        $this->seed(ReferentielSeeder::class);

        $poles = TypeStructure::with('poleElectoral')->get()
            ->mapWithKeys(fn (TypeStructure $type) => [$type->code->value => $type->poleElectoral->code]);

        $this->assertCount(6, $poles);
        foreach (CodeTypeStructure::cases() as $type) {
            $this->assertSame($type->pole(), $poles[$type->value]);
        }
        $this->assertSame(CodePoleElectoral::Departemental, $poles['TD']);
        $this->assertSame(CodePoleElectoral::Structures, $poles['CELLULE']);
    }

    public function test_une_seconde_execution_ne_cree_aucun_doublon(): void
    {
        $this->seed(ReferentielSeeder::class);
        $this->seed(ReferentielSeeder::class);

        $this->assertDatabaseCount('poles_electoraux', 2);
        $this->assertDatabaseCount('types_structure', 6);
        $this->assertDatabaseCount('departements', 12);
    }
}
