<?php

namespace Tests\Feature\Http\Acces;

use App\Enums\Acces\CodeRole;
use App\Models\Agent;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UtilisateursTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = User::factory()->avecRoles(CodeRole::Administrateur)->create();
    }

    public function test_seul_l_administrateur_gere_les_comptes(): void
    {
        $comite = User::factory()->avecRoles(CodeRole::Comite)->create();

        $this->actingAs($comite)->get(route('utilisateurs.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('utilisateurs.index'))->assertOk()->assertSee($comite->login);
    }

    /**
     * R1, R2 : création d'un compte avec ses rôles et un mot de passe provisoire affiché une seule fois.
     */
    public function test_r2_cree_un_compte_avec_ses_roles_et_un_mot_de_passe_provisoire(): void
    {
        $reponse = $this->actingAs($this->admin)->post(route('utilisateurs.store'), [
            'name' => 'KPADONOU Damien', 'login' => 'dkpadonou', 'roles' => [CodeRole::Comite->value, CodeRole::Observateur->value], 'actif' => '1',
        ]);

        $compte = User::where('login', 'dkpadonou')->firstOrFail();
        $reponse->assertRedirect(route('utilisateurs.edit', $compte))->assertSessionHas('mot_de_passe_provisoire');
        $this->assertTrue($compte->mot_de_passe_a_changer);
        $this->assertTrue(Hash::check(session('mot_de_passe_provisoire'), $compte->password));
        $this->assertEqualsCanonicalizing(['COMITE', 'OBSERVATEUR'], $compte->roles->map(fn ($r) => $r->code->value)->all());
    }

    /**
     * R3 : un agent dispose d'au plus un compte. R4 : un point focal est rattaché à une structure.
     */
    public function test_r3_r4_refuse_un_second_compte_pour_un_agent_et_un_point_focal_sans_structure(): void
    {
        $agent = Agent::factory()->create(['matricule' => '102345']);
        User::factory()->pourAgent($agent)->create();

        $this->actingAs($this->admin)->post(route('utilisateurs.store'), [
            'name' => 'Doublon', 'login' => 'doublon', 'matricule_agent' => '102345', 'roles' => [CodeRole::Electeur->value],
        ])->assertSessionHasErrors('agent_id');

        $this->post(route('utilisateurs.store'), [
            'name' => 'Point focal', 'login' => 'pf-dia', 'roles' => [CodeRole::PointFocal->value],
        ])->assertSessionHasErrors('structure_id');

        $this->post(route('utilisateurs.store'), [
            'name' => 'Point focal', 'login' => 'pf-dia', 'roles' => [CodeRole::PointFocal->value],
            'structure_id' => Structure::where('code', 'DIA')->value('id'),
        ])->assertSessionHasNoErrors();
    }

    public function test_desactive_un_compte_et_reinitialise_son_mot_de_passe(): void
    {
        $compte = User::factory()->avecRoles(CodeRole::Comite)->create();

        $this->actingAs($this->admin)->put(route('utilisateurs.update', $compte), [
            'name' => $compte->name, 'login' => $compte->login, 'roles' => [CodeRole::Comite->value],
        ])->assertSessionHasNoErrors();
        $this->assertFalse($compte->fresh()->actif);

        $this->post(route('utilisateurs.reinitialiser', $compte))->assertSessionHas('mot_de_passe_provisoire');
        $this->assertTrue($compte->fresh()->mot_de_passe_a_changer);
        $this->assertTrue(Hash::check(session('mot_de_passe_provisoire'), $compte->fresh()->password));
    }

    public function test_l_administrateur_ne_peut_pas_desactiver_son_propre_compte(): void
    {
        $this->actingAs($this->admin)->put(route('utilisateurs.update', $this->admin), [
            'name' => $this->admin->name, 'login' => $this->admin->login, 'roles' => [CodeRole::Administrateur->value],
        ])->assertSessionHasErrors('actif');

        $this->assertTrue($this->admin->fresh()->actif);
    }

    public function test_les_ecrans_de_gestion_s_affichent(): void
    {
        $this->actingAs($this->admin)->get(route('utilisateurs.create'))->assertOk()->assertSee('Créer le compte');
        $this->get(route('utilisateurs.edit', $this->admin))->assertOk()->assertSee('Réinitialiser le mot de passe');
    }
}
