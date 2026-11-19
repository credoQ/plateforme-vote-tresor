<?php

namespace Tests\Feature\Database;

use App\Enums\Referentiel\CodePoleElectoral;
use App\Models\Structure;
use App\Models\User;
use Database\Seeders\StructuresCentralesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StructuresCentralesSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * R4, R10 : l'IGS et les cellules sont des structures électorales autonomes, hors du périmètre du point focal du Cabinet.
     */
    public function test_r10_l_igs_et_les_cellules_ne_sont_pas_rattachees_au_cabinet(): void
    {
        $cabinet = Structure::where('code', 'CAB')->firstOrFail();
        Structure::whereIn('code', ['IGS', 'CRDQ', 'CSSI'])->update(['structure_parente_id' => $cabinet->id]);

        $this->seed(StructuresCentralesSeeder::class);

        $this->assertSame(0, $cabinet->structuresFilles()->count(), 'Une base chargée avec l’ancien rattachement est corrigée.');
        $this->assertSame([$cabinet->id], User::factory()->pointFocalDe($cabinet)->create()->perimetre());
        $this->assertTrue(Structure::whereIn('code', ['IGS', 'CRDQ', 'CSSI'])->get()
            ->every(fn (Structure $s) => $s->niveau === Structure::NIVEAU_STRUCTURE_ELECTORALE));
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
