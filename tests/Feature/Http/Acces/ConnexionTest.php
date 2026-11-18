<?php

namespace Tests\Feature\Http\Acces;

use App\Enums\Acces\CodeRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConnexionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_un_visiteur_est_redirige_vers_la_connexion(): void
    {
        $this->get('/')->assertRedirect('/espace');
        $this->get('/espace')->assertRedirect(route('connexion'));
        $this->get(route('connexion'))->assertOk()->assertSee('Identifiez-vous avec votre matricule');
    }

    /**
     * R1, R42 : connexion par matricule et mot de passe ; la date de dernière connexion est enregistrée.
     */
    public function test_r42_connecte_un_agent_par_son_matricule(): void
    {
        $utilisateur = User::factory()->avecRoles(CodeRole::Electeur)->create(['login' => '102345', 'password' => 'secret-2026']);

        $this->post(route('connexion.store'), ['login' => '102345', 'password' => 'secret-2026'])->assertRedirect(route('espace'));

        $this->assertAuthenticatedAs($utilisateur);
        $this->assertNotNull($utilisateur->fresh()->derniere_connexion);
        $this->get(route('espace'))->assertOk()->assertSee($utilisateur->name);
    }

    public function test_refuse_un_mauvais_mot_de_passe_et_un_compte_desactive(): void
    {
        User::factory()->create(['login' => '102345', 'password' => 'secret-2026']);
        User::factory()->inactif()->create(['login' => '200000', 'password' => 'secret-2026']);

        $this->post(route('connexion.store'), ['login' => '102345', 'password' => 'mauvais'])->assertSessionHasErrors('login');
        $this->post(route('connexion.store'), ['login' => '200000', 'password' => 'secret-2026'])->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_bloque_la_connexion_apres_cinq_echecs(): void
    {
        User::factory()->create(['login' => '102345', 'password' => 'secret-2026']);
        $this->freezeSecond();

        foreach (range(1, 5) as $essai) {
            $this->post(route('connexion.store'), ['login' => '102345', 'password' => 'mauvais']);
        }

        $this->post(route('connexion.store'), ['login' => '102345', 'password' => 'secret-2026'])
            ->assertSessionHasErrors(['login' => 'Trop de tentatives. Réessayez dans 60 secondes.']);
        $this->assertGuest();
    }

    /**
     * Le mot de passe provisoire doit être changé avant tout autre écran.
     */
    public function test_impose_le_changement_du_mot_de_passe_provisoire(): void
    {
        $utilisateur = User::factory()->create(['password' => 'Provisoire1', 'mot_de_passe_a_changer' => true]);

        $this->actingAs($utilisateur)->get(route('espace'))->assertRedirect(route('mot-de-passe.edit'));
        $this->get(route('mot-de-passe.edit'))->assertOk()->assertSee('Première connexion');

        $this->put(route('mot-de-passe.update'), [
            'mot_de_passe_actuel' => 'mauvais', 'mot_de_passe' => 'Nouveau-2026', 'mot_de_passe_confirmation' => 'Nouveau-2026',
        ])->assertSessionHasErrors('mot_de_passe_actuel');
        $this->put(route('mot-de-passe.update'), [
            'mot_de_passe_actuel' => 'Provisoire1', 'mot_de_passe' => 'court1', 'mot_de_passe_confirmation' => 'court1',
        ])->assertSessionHasErrors('mot_de_passe');

        $this->put(route('mot-de-passe.update'), [
            'mot_de_passe_actuel' => 'Provisoire1', 'mot_de_passe' => 'Nouveau-2026', 'mot_de_passe_confirmation' => 'Nouveau-2026',
        ])->assertRedirect(route('espace'));

        $utilisateur->refresh();
        $this->assertFalse($utilisateur->mot_de_passe_a_changer);
        $this->assertTrue(Hash::check('Nouveau-2026', $utilisateur->password));
        $this->get(route('espace'))->assertOk();
    }

    public function test_deconnecte_l_utilisateur(): void
    {
        $this->actingAs(User::factory()->create())->post(route('deconnexion'))->assertRedirect(route('connexion'));

        $this->assertGuest();
    }
}
