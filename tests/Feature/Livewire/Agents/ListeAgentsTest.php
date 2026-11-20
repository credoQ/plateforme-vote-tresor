<?php

namespace Tests\Feature\Livewire\Agents;

use App\Enums\Acces\CodeRole;
use App\Livewire\Agents\ListeAgents;
use App\Models\Agent;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Liste des agents en Livewire : filtres appliqués sans rechargement, périmètre du point focal (R4).
 */
class ListeAgentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_la_page_des_agents_affiche_le_composant(): void
    {
        $this->actingAs(User::factory()->avecRoles(CodeRole::Administrateur)->create())
            ->get(route('agents.index'))->assertOk()->assertSeeLivewire(ListeAgents::class);
    }

    public function test_filtre_les_agents_pendant_la_saisie_et_revient_a_la_premiere_page(): void
    {
        $dia = Structure::where('code', 'DIA')->firstOrFail();
        Agent::factory()->femme()->affecteA($dia, Carbon::parse('2020-01-01'))->create(['nom' => 'AHOUANSOU']);
        Agent::factory()->homme()->affecteA($dia, Carbon::parse('2020-01-01'))->create(['nom' => 'DOSSOU']);

        Livewire::actingAs(User::factory()->avecRoles(CodeRole::Administrateur)->create())
            ->withQueryParams(['page' => 2])
            ->test(ListeAgents::class)
            ->set('recherche', 'dossou')
            ->assertSet('paginators.page', 1)
            ->assertSee('DOSSOU')->assertDontSee('AHOUANSOU')
            ->set('recherche', '')
            ->set('sexe', 'F')
            ->assertSee('AHOUANSOU')->assertDontSee('DOSSOU')
            ->assertSee('Réinitialiser');
    }

    /**
     * R4 : le point focal ne voit que les agents de son périmètre, et ne peut pas en sortir par un filtre.
     */
    public function test_r4_le_point_focal_reste_dans_son_perimetre(): void
    {
        $dia = Structure::where('code', 'DIA')->firstOrFail();
        $tdZou = Structure::where('code', 'TD-ZOU')->firstOrFail();
        Agent::factory()->affecteA($dia, Carbon::parse('2020-01-01'))->create(['nom' => 'INTERNE']);
        Agent::factory()->affecteA($tdZou, Carbon::parse('2020-01-01'))->create(['nom' => 'EXTERNE']);

        Livewire::actingAs(User::factory()->pointFocalDe($dia)->create())
            ->test(ListeAgents::class)
            ->assertSee('INTERNE')->assertDontSee('EXTERNE')
            ->set('structure', (string) $tdZou->id)
            ->assertDontSee('EXTERNE');
    }

    public function test_un_electeur_ne_peut_pas_afficher_la_liste(): void
    {
        Livewire::actingAs(User::factory()->pourAgent(Agent::factory()->create())->avecRoles(CodeRole::Electeur)->create())
            ->test(ListeAgents::class)
            ->assertForbidden();
    }
}
