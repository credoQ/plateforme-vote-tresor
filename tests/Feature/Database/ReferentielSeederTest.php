<?php

namespace Tests\Feature\Database;

use App\Enums\Referentiel\CodePoleElectoral;
use App\Enums\Referentiel\CodeTypeStructure;
use App\Models\Departement;
use App\Models\Structure;
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

    /**
     * R9 : une Trésorerie départementale par département, située dans ce département.
     */
    public function test_r9_cree_une_td_dans_chacun_des_12_departements(): void
    {
        $this->seed(ReferentielSeeder::class);

        $this->assertSame(12, Departement::count());
        Departement::with('structures')->each(function (Departement $departement): void {
            $this->assertSame(['TD-'.$departement->code], $departement->structures->pluck('code')->all());
        });
    }

    /**
     * R10 : les cellules et l'IGS sont des structures autonomes rattachées au Cabinet.
     */
    public function test_r10_rattache_les_cellules_et_l_igs_au_cabinet_comme_structures_autonomes(): void
    {
        $this->seed(ReferentielSeeder::class);

        $rattachees = Structure::where('code', 'CAB')->firstOrFail()->structuresFilles;

        $this->assertEqualsCanonicalizing(['IGS', 'CRDQ', 'CSSI'], $rattachees->pluck('code')->all());
        $this->assertTrue($rattachees->every(fn (Structure $s) => $s->niveau === Structure::NIVEAU_STRUCTURE_ELECTORALE));
    }

    public function test_une_seconde_execution_ne_cree_aucun_doublon(): void
    {
        $this->seed(ReferentielSeeder::class);
        $this->seed(ReferentielSeeder::class);

        $this->assertDatabaseCount('poles_electoraux', 2);
        $this->assertDatabaseCount('types_structure', 6);
        $this->assertDatabaseCount('departements', 12);
        $this->assertDatabaseCount('structures', count(ReferentielSeeder::STRUCTURES) + count(ReferentielSeeder::DEPARTEMENTS));
    }
}
