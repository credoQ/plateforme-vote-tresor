<?php

namespace Tests\Feature\Http\Agents;

use App\Enums\Acces\CodeRole;
use App\Enums\Audit\ActionAuditee;
use App\Livewire\Agents\FormulaireAgent;
use App\Models\Affectation;
use App\Models\Agent;
use App\Models\JournalAudit;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class GestionDesAgentsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-11-18 10:00:00'));
        $this->admin = User::factory()->avecRoles(CodeRole::Administrateur)->create();
    }

    public function test_filtre_l_historique_des_affectations_d_un_agent(): void
    {
        $agent = Agent::factory()->affecteA($this->structure('DIA'), Carbon::parse('2024-01-01'))->create();
        Affectation::factory()->terminee('2023-12-31')->create(['agent_id' => $agent->id, 'structure_id' => $this->structure('TD-ZOU')->id, 'date_debut' => '2020-01-01']);

        $this->actingAs($this->admin)->get(route('agents.show', [$agent, 'periode' => 'terminees']))->assertOk()
            ->assertSee('<b class="chiffres">1</b> affectation(s)', false)->assertSee('31/12/2023');
        $this->get(route('agents.show', [$agent, 'recherche' => 'td-zou']))->assertSee('<b class="chiffres">1</b> affectation(s)', false);
        $this->get(route('agents.show', [$agent, 'recherche' => 'inconnue']))->assertSee('Aucune affectation ne correspond aux filtres.');
    }

    public function test_la_fiche_presente_l_affectation_actuelle_et_la_duree_de_chaque_affectation(): void
    {
        $agent = Agent::factory()->affecteA($this->structure('DIA'), Carbon::parse('2024-01-01'))->create();
        Affectation::factory()->terminee('2023-12-31')->create(['agent_id' => $agent->id, 'structure_id' => $this->structure('TD-ZOU')->id, 'date_debut' => '2020-01-01']);

        $this->actingAs($this->admin)->get(route('agents.show', $agent))->assertOk()
            ->assertSeeInOrder(['Affectation actuelle', 'DIA', 'Depuis le 01/01/2024 · 2 ans 10 mois', 'Muter l’agent'], false)
            ->assertSee('4 ans')->assertSee('En service')
            ->assertDontSee('affectation(s)', false);
    }

    /**
     * R11, R12 : saisie manuelle d'un agent avec son affectation, tracée au journal.
     */
    public function test_r11_saisit_un_agent_et_son_affectation(): void
    {
        $dia = $this->structure('DIA');

        $this->actingAs($this->admin)->get(route('agents.create'))->assertOk()->assertSee('Enregistrer l’agent');
        $this->formulaire()
            ->set(['fiche.matricule' => '300100', 'fiche.npi' => '1234567890', 'fiche.nom' => 'ahouansou', 'fiche.prenoms' => 'Clarisse', 'fiche.sexe' => 'F',
                'fiche.email' => 'clarisse@example.test', 'fiche.date_naissance' => '1985-03-12', 'fiche.fonction' => '',
                'structureId' => (string) $dia->id, 'dateDebut' => '2024-01-02'])
            ->call('enregistrer')
            ->assertHasNoErrors()->assertRedirect(route('agents.show', Agent::where('matricule', '300100')->firstOrFail()));

        $agent = Agent::where('matricule', '300100')->firstOrFail();
        $this->assertSame('AHOUANSOU', $agent->nom);
        $this->assertNull($agent->fonction);
        $this->assertSame('DIA', $agent->affectationLe()->structure->code);
        $this->assertSame(1, JournalAudit::where('action', ActionAuditee::AgentEnregistre->value)->count());

        $this->formulaire()
            ->set(['fiche.matricule' => '300100', 'fiche.nom' => 'X', 'fiche.prenoms' => 'Y', 'fiche.sexe' => 'M', 'structureId' => (string) $dia->id, 'dateDebut' => '2024-01-02'])
            ->call('enregistrer')
            ->assertHasErrors('matricule')->assertDispatched('formulaire-erreurs')->assertNoRedirect();
        $this->assertSame(1, Agent::where('matricule', '300100')->count(), 'Rien n’est enregistré en cas d’erreur.');
    }

    /**
     * R12, R13 : mutation et fin d'affectation depuis la fiche.
     */
    public function test_r12_mute_puis_met_fin_a_l_affectation(): void
    {
        $agent = Agent::factory()->femme()->affecteA($this->structure('DIA'), Carbon::parse('2020-01-01'))->create();

        $this->actingAs($this->admin)->get(route('agents.show', $agent))->assertOk()->assertSee('Enregistrer la mutation')->assertSee('En cours');
        $this->post(route('agents.affecter', $agent), ['structure_id' => $this->structure('TD-ZOU')->id, 'date_debut' => '2026-11-01'])->assertSessionHasNoErrors();
        $this->assertSame('TD-ZOU', $agent->affectationLe()->structure->code);
        $this->assertSame(2, $agent->affectations()->count());

        $this->post(route('agents.affecter', $agent), ['structure_id' => $this->structure('DR')->id, 'date_debut' => '2019-01-01'])->assertSessionHasErrors('date_debut');

        $this->post(route('agents.terminer', $agent), ['date_fin' => '2026-11-18'])->assertSessionHasNoErrors();
        $this->assertNull($agent->affectationLe(Carbon::parse('2026-11-19')));
        $this->assertSame(['AGENT_AFFECTE', 'AFFECTATION_TERMINEE'], JournalAudit::orderBy('id')->pluck('action')->map->value->all());
    }

    public function test_modifie_la_fiche_d_un_agent(): void
    {
        $agent = Agent::factory()->homme()->affecteA($this->structure('DIA'))->create();

        $this->actingAs($this->admin)->get(route('agents.edit', $agent))->assertOk();
        $this->formulaire($agent)
            ->set(['fiche.nom' => 'dossou', 'fiche.prenoms' => 'Marc', 'fiche.email' => 'marc@example.test', 'fiche.fonction' => 'Chef de service'])
            ->call('enregistrer')
            ->assertHasNoErrors()->assertRedirect(route('agents.show', $agent));

        $this->assertSame('Chef de service', $agent->fresh()->fonction);
        $this->assertSame('DOSSOU', $agent->fresh()->nom);
    }

    /**
     * R4 : le point focal d'une TD contrôle ses agents et ceux des Trésoreries communales rattachées, en lecture seule.
     */
    public function test_r4_le_point_focal_controle_les_agents_de_sa_structure_en_lecture_seule(): void
    {
        $td = $this->structure('TD-ATL');
        $tc = $this->structure('TC-OUIDAH');
        $agentTc = Agent::factory()->affecteA($tc)->create(['nom' => 'HOUNTON']);
        $agentDia = Agent::factory()->affecteA($this->structure('DIA'))->create(['nom' => 'ZINSOU']);
        $pointFocal = User::factory()->pointFocalDe($td)->create();

        $this->actingAs($pointFocal)->get(route('agents.index'))
            ->assertOk()->assertSee('Agents de ma structure')->assertSee('HOUNTON')->assertDontSee('ZINSOU')->assertDontSee('Saisir un agent');
        $this->get(route('agents.show', $agentTc))->assertOk()->assertDontSee('Enregistrer la mutation');
        $this->get(route('agents.show', $agentDia))->assertForbidden();
        $this->get(route('agents.create'))->assertForbidden();
        $this->post(route('agents.affecter', $agentTc), ['structure_id' => $td->id, 'date_debut' => '2026-11-18'])->assertForbidden();
    }

    public function test_filtre_les_agents_et_refuse_l_electeur(): void
    {
        Agent::factory()->femme()->affecteA($this->structure('DIA'))->create(['nom' => 'AHOUANSOU']);
        Agent::factory()->homme()->affecteA($this->structure('DR'))->create(['nom' => 'DOSSOU']);
        Agent::factory()->femme()->create(['nom' => 'PARTIE']);

        $this->actingAs($this->admin)->get(route('agents.index', ['sexe' => 'F']))->assertSee('AHOUANSOU')->assertDontSee('DOSSOU')->assertDontSee('PARTIE');
        $this->get(route('agents.index', ['situation' => 'hors-service']))->assertSee('PARTIE')->assertDontSee('AHOUANSOU');
        $this->get(route('agents.index', ['recherche' => 'dossou']))->assertSee('DOSSOU')->assertDontSee('AHOUANSOU');

        $electeur = User::factory()->pourAgent(Agent::factory()->create())->avecRoles(CodeRole::Electeur)->create();
        $this->actingAs($electeur)->get(route('agents.index'))->assertForbidden();
    }

    private function structure(string $code): Structure
    {
        return Structure::where('code', $code)->firstOrFail();
    }

    private function formulaire(?Agent $agent = null): Testable
    {
        return Livewire::test(FormulaireAgent::class, ['agent' => $agent]);
    }
}
