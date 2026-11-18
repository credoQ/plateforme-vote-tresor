<?php

namespace Tests\Feature\Models;

use App\Enums\Referentiel\CodePoleElectoral;
use App\Models\Structure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StructureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * R10 : seules les structures actives de niveau 1 sont des structures électorales.
     */
    public function test_r10_les_structures_electorales_excluent_les_services_et_les_structures_inactives(): void
    {
        $dia = Structure::where('code', 'DIA')->firstOrFail();
        Structure::factory()->serviceDe($dia)->create(['code' => 'DIA-SED']);
        $dmg = Structure::where('code', 'DMG')->firstOrFail();
        $dmg->update(['actif' => false]);

        $codes = Structure::electorales()->pluck('code');

        $this->assertContains('DIA', $codes);
        $this->assertNotContains('DIA-SED', $codes);
        $this->assertNotContains('DMG', $codes);
    }

    /**
     * R7 : une structure appartient au pôle de son type.
     */
    public function test_r7_filtre_les_structures_par_pole_electoral(): void
    {
        $departemental = Structure::duPole(CodePoleElectoral::Departemental)->pluck('code');
        $structures = Structure::duPole(CodePoleElectoral::Structures)->pluck('code');

        $this->assertCount(12, $departemental);
        $this->assertTrue($departemental->every(fn (string $code) => str_starts_with($code, 'TD-')));
        $this->assertContains('TGE', $structures);
        $this->assertContains('ACD', $structures);
        $this->assertNotContains('TD-ATL', $structures);
    }
}
