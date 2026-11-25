<?php

namespace Tests\Feature;

use App\Enums\Acces\CodeRole;
use App\Http\Middleware\EnvoiSansRechargement;
use App\Models\Agent;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Formulaires d'action de la console envoyés sans recharger la page : la redirection devient une adresse en JSON,
 * et le message de succès ou d'erreur reste en session pour la page ouverte ensuite.
 */
class EnvoiSansRechargementTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_redirection_d_une_action_devient_une_adresse_et_garde_le_message(): void
    {
        $structure = Structure::where('code', 'DIA')->firstOrFail();
        $this->actingAs(User::factory()->avecRoles(CodeRole::Administrateur)->create());

        $this->post(route('structures.desactiver', $structure), [], [EnvoiSansRechargement::ENTETE => '1'])
            ->assertOk()
            ->assertExactJson(['redirection' => route('structures.show', $structure)])
            ->assertSessionHas('succes', 'La structure DIA est désactivée.');
        $this->assertFalse($structure->fresh()->actif);

        $this->post(route('structures.reactiver', $structure))->assertRedirect(route('structures.show', $structure));
    }

    /**
     * R5 : le refus garde son message en session ; il s'affiche sur la page ouverte ensuite.
     */
    public function test_r5_un_refus_garde_ses_erreurs_en_session(): void
    {
        $structure = Structure::where('code', 'DIA')->firstOrFail();
        Agent::factory()->affecteA($structure)->create();
        $this->actingAs(User::factory()->avecRoles(CodeRole::Administrateur)->create());

        $this->from(route('structures.show', $structure))
            ->post(route('structures.desactiver', $structure), [], [EnvoiSansRechargement::ENTETE => '1'])
            ->assertExactJson(['redirection' => route('structures.show', $structure)])
            ->assertSessionHasErrors('structure');
        $this->assertTrue($structure->fresh()->actif);
    }
}
