<?php

namespace Tests\Feature\Database;

use App\Enums\Referentiel\CodePoleElectoral;
use App\Models\Structure;
use Database\Seeders\StructuresCentralesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StructuresCentralesSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * R10 : les cellules et l'IGS sont des structures autonomes rattachées au Cabinet.
     */
    public function test_r10_rattache_les_cellules_et_l_igs_au_cabinet_comme_structures_autonomes(): void
    {
        $this->seed(StructuresCentralesSeeder::class);

        $rattachees = Structure::where('code', 'CAB')->firstOrFail()->structuresFilles;

        $this->assertEqualsCanonicalizing(['IGS', 'CRDQ', 'CSSI'], $rattachees->pluck('code')->all());
        $this->assertTrue($rattachees->every(fn (Structure $s) => $s->niveau === Structure::NIVEAU_STRUCTURE_ELECTORALE));
    }

    /**
     * R7 : la DPCP et la TGE sont des directions du pôle structures.
     */
    public function test_r7_classe_la_dpcp_et_la_tge_comme_directions_du_pole_structures(): void
    {
        $this->seed(StructuresCentralesSeeder::class);

        $this->assertSame(
            ['DPCP' => 'DIRECTION_CENTRALE', 'TGE' => 'DIRECTION_CENTRALE'],
            Structure::with('typeStructure')->whereIn('code', ['DPCP', 'TGE'])->orderBy('code')->get()
                ->mapWithKeys(fn (Structure $s) => [$s->code => $s->typeStructure->code->value])->all(),
        );
        $this->assertSame(0, Structure::whereIn('code', array_keys(StructuresCentralesSeeder::STRUCTURES))->duPole(CodePoleElectoral::Departemental)->count());
    }
}
