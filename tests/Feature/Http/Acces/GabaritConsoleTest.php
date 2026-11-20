<?php

namespace Tests\Feature\Http\Acces;

use App\Enums\Acces\CodeRole;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gabarit de la console : déconnexion et espace électeur dans le menu de l'avatar, pied de page institutionnel.
 */
class GabaritConsoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_le_menu_de_l_avatar_porte_la_deconnexion_et_le_pied_de_page_le_ministere(): void
    {
        $administrateur = User::factory()->avecRoles(CodeRole::Administrateur)->create();

        $this->actingAs($administrateur)->get(route('utilisateurs.index'))->assertOk()
            ->assertSee('id="menu-profil"', false)
            ->assertSee('action="'.route('logout').'"', false)
            ->assertSee('Se déconnecter')
            ->assertDontSee('Mon espace électeur')
            ->assertSee('Ministère de l’Économie et des Finances chargé des Finances et de la Microfinance')
            ->assertSee('© '.date('Y').' DIA/DGTCP');
    }

    public function test_un_compte_rattache_a_un_agent_retrouve_son_espace_electeur_dans_le_menu_de_l_avatar(): void
    {
        $administrateur = User::factory()->pourAgent(Agent::factory()->create())->avecRoles(CodeRole::Administrateur)->create();

        $this->actingAs($administrateur)->get(route('utilisateurs.index'))->assertOk()
            ->assertSee('href="'.route('espace').'" class="menu-profil__lien"', false);
    }
}
