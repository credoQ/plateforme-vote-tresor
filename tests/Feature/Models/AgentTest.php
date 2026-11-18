<?php

namespace Tests\Feature\Models;

use App\Models\Affectation;
use App\Models\Agent;
use App\Models\Structure;
use Database\Seeders\ReferentielSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AgentTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = ReferentielSeeder::class;

    /**
     * R13 : en service = une affectation en cours dans une structure active à la date donnée.
     */
    public function test_r13_un_agent_est_en_service_s_il_a_une_affectation_en_cours_dans_une_structure_active(): void
    {
        $dia = Structure::where('code', 'DIA')->firstOrFail();
        $dmg = Structure::where('code', 'DMG')->firstOrFail();
        $gel = Carbon::parse('2026-10-01');

        $enService = Agent::factory()->affecteA($dia, Carbon::parse('2020-01-01'))->create();
        $parti = Agent::factory()->create();
        Affectation::factory()->for($parti)->for($dia)->create(['date_debut' => '2015-01-01', 'date_fin' => '2026-09-30']);
        $arriveApresLeGel = Agent::factory()->affecteA($dia, Carbon::parse('2026-10-02'))->create();
        $sansAffectation = Agent::factory()->create();
        $dansStructureInactive = Agent::factory()->affecteA($dmg, Carbon::parse('2020-01-01'))->create();
        $dmg->update(['actif' => false]);

        $ids = Agent::enServiceLe($gel)->pluck('id');

        $this->assertContains($enService->id, $ids);
        $this->assertNotContains($parti->id, $ids);
        $this->assertNotContains($arriveApresLeGel->id, $ids);
        $this->assertNotContains($sansAffectation->id, $ids);
        $this->assertNotContains($dansStructureInactive->id, $ids);
    }

    public function test_filtre_les_agents_de_sexe_feminin(): void
    {
        $femme = Agent::factory()->femme()->create();
        $homme = Agent::factory()->homme()->create();

        $ids = Agent::femmes()->pluck('id');

        $this->assertContains($femme->id, $ids);
        $this->assertNotContains($homme->id, $ids);
    }
}
