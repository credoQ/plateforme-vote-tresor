<?php

namespace Tests\Feature\Actions\Agents;

use App\Actions\Agents\AffecterAgent;
use App\Actions\Agents\TerminerAffectation;
use App\Models\Agent;
use App\Models\Structure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AffecterAgentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * R12 : une mutation clôt l'affectation en cours la veille et conserve l'historique.
     */
    public function test_r12_une_mutation_clot_l_affectation_en_cours_et_conserve_l_historique(): void
    {
        $agent = Agent::factory()->affecteA($this->structure('DIA'), Carbon::parse('2020-01-06'))->create();

        $this->affecter($agent, 'TD-ZOU', '2025-09-01');

        $historique = $agent->affectations()->with('structure')->get();
        $this->assertSame(['TD-ZOU', 'DIA'], $historique->pluck('structure.code')->all());
        $this->assertSame('2025-08-31', $historique[1]->date_fin->toDateString());
        $this->assertNull($historique[0]->date_fin);
        $this->assertSame('DIA', $agent->affectationLe(Carbon::parse('2025-08-31'))->structure->code);
        $this->assertSame('TD-ZOU', $agent->affectationLe(Carbon::parse('2025-09-01'))->structure->code);
    }

    public function test_r12_reaffecter_a_la_structure_actuelle_ne_cree_pas_d_affectation(): void
    {
        $agent = Agent::factory()->affecteA($this->structure('DMG'))->create();

        $this->affecter($agent, 'DMG', '2026-01-01');

        $this->assertCount(1, $agent->affectations);
    }

    /**
     * R12 : un agent n'a qu'une structure à une date donnée.
     */
    public function test_r12_rejette_une_affectation_qui_chevauche_l_historique(): void
    {
        $agent = Agent::factory()->affecteA($this->structure('DIA'), Carbon::parse('2024-01-01'))->create();

        $this->expectException(ValidationException::class);

        $this->affecter($agent, 'DR', '2023-06-01');
    }

    public function test_rejette_une_affectation_dans_une_structure_inactive(): void
    {
        $agent = Agent::factory()->create();
        $this->structure('DR')->update(['actif' => false]);

        $this->expectException(ValidationException::class);

        $this->affecter($agent, 'DR', '2026-01-01');
    }

    /**
     * R13 : un agent dont l'affectation est terminée n'est plus en service.
     */
    public function test_r13_terminer_l_affectation_met_fin_au_service(): void
    {
        $agent = Agent::factory()->affecteA($this->structure('TGE'), Carbon::parse('2019-02-01'))->create();

        app(TerminerAffectation::class)->handle($agent, Carbon::parse('2026-06-30'));

        $this->assertTrue(Agent::enServiceLe(Carbon::parse('2026-06-30'))->whereKey($agent->id)->exists());
        $this->assertFalse(Agent::enServiceLe(Carbon::parse('2026-07-01'))->whereKey($agent->id)->exists());
    }

    private function affecter(Agent $agent, string $code, string $date): void
    {
        app(AffecterAgent::class)->handle($agent, $this->structure($code), Carbon::parse($date));
    }

    private function structure(string $code): Structure
    {
        return Structure::where('code', $code)->firstOrFail();
    }
}
