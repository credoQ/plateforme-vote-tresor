<?php

namespace Tests\Feature\Http\Vote;

use App\Enums\Acces\CodeRole;
use App\Models\Agent;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * L'électeur a son propre espace, sans la console de gestion ; un profil de gestion qui vote passe de l'un à l'autre.
 */
class EspaceElecteurTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_l_electeur_n_a_que_l_espace_electeur(): void
    {
        $electeur = User::factory()->avecRoles(CodeRole::Electeur)->pourAgent($this->agent())->create();

        $this->actingAs($electeur)->get(route('espace'))->assertOk()
            ->assertSee('portail__entete', false)->assertDontSee('class="sidebar"', false)->assertDontSee('Console de gestion');
        $this->get(route('palmares'))->assertOk()->assertSee('portail__entete', false)->assertDontSee('class="sidebar"', false);
    }

    public function test_un_membre_du_comite_qui_vote_passe_de_la_console_a_son_espace(): void
    {
        $comite = User::factory()->avecRoles(CodeRole::Comite, CodeRole::Electeur)->pourAgent($this->agent())->create();

        $this->actingAs($comite)->get(route('espace'))->assertOk()->assertSee('Console de gestion');
        $this->get(route('palmares'))->assertOk()->assertSee('class="sidebar"', false)->assertSee('Mon espace électeur');
    }

    public function test_un_compte_de_gestion_sans_fiche_agent_est_renvoye_vers_la_console(): void
    {
        $administrateur = User::factory()->avecRoles(CodeRole::Administrateur)->create();

        $this->actingAs($administrateur)->get(route('espace'))->assertRedirect(route('tableau-de-bord'));
        $this->get(route('tableau-de-bord'))->assertOk()->assertDontSee('Mon espace électeur');
    }

    private function agent(): Agent
    {
        return Agent::factory()->femme()->affecteA(Structure::where('code', 'DIA')->firstOrFail(), Carbon::parse('2020-01-01'))->create();
    }
}
