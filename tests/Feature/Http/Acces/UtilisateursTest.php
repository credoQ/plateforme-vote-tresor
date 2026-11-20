<?php

namespace Tests\Feature\Http\Acces;

use App\Enums\Acces\CodeRole;
use App\Models\Agent;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
     * R1, R2 : création d'un compte avec ses rôles ; l'adresse e-mail est obligatoire pour recevoir le code (R42).
     */
    public function test_r2_cree_un_compte_avec_ses_roles_et_son_adresse(): void
    {
        $this->actingAs($this->admin)->post(route('utilisateurs.store'), [
            'name' => 'KPADONOU Damien', 'login' => 'dkpadonou', 'roles' => [CodeRole::Comite->value], 'actif' => '1',
        ])->assertSessionHasErrors('email');

        $this->post(route('utilisateurs.store'), [
            'name' => 'KPADONOU Damien', 'login' => 'dkpadonou', 'email' => 'dkpadonou@example.test',
            'roles' => [CodeRole::Comite->value, CodeRole::Observateur->value], 'actif' => '1',
        ])->assertSessionHasNoErrors();

        $compte = User::where('login', 'dkpadonou')->firstOrFail();
        $this->assertNull($compte->password);
        $this->assertSame('dkpadonou@example.test', $compte->adresseDeConnexion());
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
            'name' => 'Doublon', 'login' => '102345', 'email' => 'doublon@example.test', 'roles' => [CodeRole::Electeur->value],
        ])->assertSessionHasErrors('login');

        $this->post(route('utilisateurs.store'), [
            'name' => 'Point focal', 'login' => 'pf-dia', 'email' => 'pf@example.test', 'roles' => [CodeRole::PointFocal->value],
        ])->assertSessionHasErrors('structure_id');

        $this->post(route('utilisateurs.store'), [
            'name' => 'Point focal', 'login' => 'pf-dia', 'email' => 'pf@example.test', 'roles' => [CodeRole::PointFocal->value],
            'structure_id' => Structure::where('code', 'DIA')->value('id'),
        ])->assertSessionHasNoErrors();
    }

    public function test_desactive_un_compte(): void
    {
        $compte = User::factory()->avecRoles(CodeRole::Comite)->create();

        $this->actingAs($this->admin)->put(route('utilisateurs.update', $compte), [
            'name' => $compte->name, 'login' => $compte->login, 'email' => $compte->email, 'roles' => [CodeRole::Comite->value],
        ])->assertSessionHasNoErrors();

        $this->assertFalse($compte->fresh()->actif);
    }

    public function test_l_administrateur_ne_peut_pas_desactiver_son_propre_compte(): void
    {
        $this->actingAs($this->admin)->put(route('utilisateurs.update', $this->admin), [
            'name' => $this->admin->name, 'login' => $this->admin->login, 'email' => $this->admin->email, 'roles' => [CodeRole::Administrateur->value],
        ])->assertSessionHasErrors('actif');

        $this->assertTrue($this->admin->fresh()->actif);
    }

    public function test_les_ecrans_de_gestion_s_affichent(): void
    {
        $this->actingAs($this->admin)->get(route('utilisateurs.create'))->assertOk()->assertSee('Créer le compte');
        $this->get(route('utilisateurs.edit', $this->admin))->assertOk()->assertSee('Enregistrer le compte');
    }

    /**
     * R3 : l'identifiant qui est le matricule d'un agent sans compte lie le compte à cet agent ; son adresse suffit.
     */
    public function test_r3_lie_le_compte_a_l_agent_qui_porte_l_identifiant(): void
    {
        $agent = Agent::factory()->create(['matricule' => '104455', 'email' => 'agent@example.test']);

        $this->actingAs($this->admin)->post(route('utilisateurs.store'), [
            'name' => $agent->nomComplet(), 'login' => '104455', 'roles' => [CodeRole::Comite->value], 'actif' => '1',
            'structure_id' => Structure::where('code', 'DIA')->value('id'),
        ])->assertSessionHasNoErrors();

        $compte = User::where('login', '104455')->firstOrFail();
        $this->assertSame($agent->id, $compte->agent_id);
        $this->assertNull($compte->structure_id);
        $this->assertSame('agent@example.test', $compte->adresseDeConnexion());
    }

    /**
     * R3 : un compte sans agent se lie depuis la liste à un agent qui n'a pas encore de compte, une seule fois.
     */
    public function test_r3_lie_un_compte_existant_a_un_agent_sans_compte(): void
    {
        $agent = Agent::factory()->create(['matricule' => '106677']);
        $compte = User::factory()->avecRoles(CodeRole::Comite)->create(['login' => 'comite-1']);

        $this->actingAs($this->admin)->get(route('utilisateurs.index'))->assertOk()->assertSee('Lier à un agent')->assertSee('106677 · '.$agent->nomComplet());
        $this->post(route('utilisateurs.lier-agent', $compte), ['agent' => '106677 · '.$agent->nomComplet()])->assertSessionHasNoErrors();
        $this->assertSame($agent->id, $compte->fresh()->agent_id);

        $autre = User::factory()->avecRoles(CodeRole::Observateur)->create();
        $this->post(route('utilisateurs.lier-agent', $autre), ['agent' => '106677'])->assertSessionHasErrors('agent');
        $this->assertNull($autre->fresh()->agent_id);
    }
}
