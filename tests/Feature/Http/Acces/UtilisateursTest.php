<?php

namespace Tests\Feature\Http\Acces;

use App\Enums\Acces\CodeRole;
use App\Livewire\Acces\FormulaireUtilisateur;
use App\Models\Agent;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
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
        $this->formulaire()
            ->set('name', 'KPADONOU Damien')->set('login', 'dkpadonou')->set('roles', [CodeRole::Comite->value])
            ->call('enregistrer')->assertHasErrors('email')
            ->set('email', 'dkpadonou@example.test')->set('roles', [CodeRole::Comite->value, CodeRole::Observateur->value])
            ->call('enregistrer')->assertHasNoErrors()->assertRedirect();

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

        $this->formulaire()
            ->set('name', 'Doublon')->set('login', '102345')->set('email', 'doublon@example.test')->set('roles', [CodeRole::Electeur->value])
            ->call('enregistrer')->assertHasErrors('login');

        $this->formulaire()
            ->set('name', 'Point focal')->set('login', 'pf-dia')->set('email', 'pf@example.test')->set('roles', [CodeRole::PointFocal->value])
            ->call('enregistrer')->assertHasErrors('structure_id')
            ->set('structureId', (string) Structure::where('code', 'DIA')->value('id'))
            ->call('enregistrer')->assertHasNoErrors();
    }

    /**
     * R2, R3 : le rôle Électeur est réservé aux comptes liés à un agent ; un compte sans agent ne peut pas le recevoir.
     */
    public function test_r3_refuse_le_role_electeur_a_un_compte_sans_agent(): void
    {
        $this->formulaire()
            ->set('name', 'Observatrice externe')->set('login', 'obs.externe')->set('email', 'obs@example.test')
            ->set('roles', [CodeRole::Observateur->value, CodeRole::Electeur->value])
            ->call('enregistrer')->assertHasErrors('role_electeur')
            ->assertDispatched('erreur', message: 'Le rôle Électeur est réservé aux comptes liés à un agent : l’identifiant doit être le matricule d’un agent.');
        $this->assertDatabaseMissing('users', ['login' => 'obs.externe']);

        $agent = Agent::factory()->create(['matricule' => '104999', 'email' => 'agent.104999@example.test']);
        $this->formulaire()
            ->set('name', $agent->nomComplet())->set('login', '104999')->set('email', 'compte.104999@example.test')->set('roles', [CodeRole::Electeur->value])
            ->call('enregistrer')->assertHasNoErrors();
    }

    public function test_desactive_un_compte(): void
    {
        $compte = User::factory()->avecRoles(CodeRole::Comite)->create();

        $this->formulaire($compte)->set('actif', false)->call('enregistrer')->assertHasNoErrors();

        $this->assertFalse($compte->fresh()->actif);
    }

    /**
     * R42 : l'adresse de connexion est obligatoire aussi en modification ; un compte sans adresse se voit proposer
     * celle de la fiche de son agent.
     */
    public function test_r42_l_email_de_connexion_est_obligatoire_en_modification(): void
    {
        $agent = Agent::factory()->create(['email' => 'agent.fiche@example.test']);
        $compte = User::factory()->pourAgent($agent)->avecRoles(CodeRole::Electeur)->create(['email' => null]);

        $this->formulaire($compte)->assertSet('email', 'agent.fiche@example.test')
            ->set('email', '')->call('enregistrer')->assertHasErrors(['email' => 'Une adresse e-mail est nécessaire pour recevoir le code de connexion.'])
            ->set('email', 'agent.fiche@example.test')->call('enregistrer')->assertHasNoErrors();

        $this->assertSame('agent.fiche@example.test', $compte->fresh()->email);
    }

    public function test_l_administrateur_ne_peut_pas_desactiver_son_propre_compte(): void
    {
        $this->formulaire($this->admin)->set('actif', false)->call('enregistrer')->assertHasErrors('actif');

        $this->assertTrue($this->admin->fresh()->actif);
    }

    public function test_les_ecrans_de_gestion_s_affichent(): void
    {
        $this->actingAs($this->admin)->get(route('utilisateurs.create'))->assertOk()->assertSee('Créer le compte');
        $this->get(route('utilisateurs.edit', $this->admin))->assertOk()->assertSee('Enregistrer le compte');
    }

    /**
     * R3 : l'identifiant qui est le matricule d'un agent sans compte lie le compte à cet agent. R42 : l'adresse
     * de connexion est obligatoire à la création du compte, même si la fiche de l'agent en porte une.
     */
    public function test_r3_lie_le_compte_a_l_agent_qui_porte_l_identifiant(): void
    {
        $agent = Agent::factory()->create(['matricule' => '104455', 'email' => 'agent@example.test']);
        $this->formulaire()
            ->set('name', $agent->nomComplet())->set('login', '104455')->set('roles', [CodeRole::Comite->value])
            ->set('structureId', (string) Structure::where('code', 'DIA')->value('id'))
            ->call('enregistrer')->assertHasErrors('email')
            ->set('email', 'compte@example.test')
            ->call('enregistrer')->assertHasNoErrors();

        $compte = User::where('login', '104455')->firstOrFail();
        $this->assertSame($agent->id, $compte->agent_id);
        $this->assertNull($compte->structure_id);
        $this->assertSame('compte@example.test', $compte->adresseDeConnexion());
    }

    /**
     * R3 : un compte sans agent se lie depuis la liste à un agent qui n'a pas encore de compte, une seule fois.
     */
    public function test_r3_lie_un_compte_existant_a_un_agent_sans_compte(): void
    {
        $agent = Agent::factory()->create(['matricule' => '106677']);
        $compte = User::factory()->avecRoles(CodeRole::Comite)->create(['login' => 'comite-1']);

        $this->actingAs($this->admin)->get(route('utilisateurs.index'))->assertOk()->assertSee('Lier à un agent')->assertSee('<option value="106677" data-detail="106677">'.$agent->nomComplet().'</option>', false);
        $this->post(route('utilisateurs.lier-agent', $compte), ['agent' => '106677'])->assertSessionHasNoErrors();
        $this->assertSame($agent->id, $compte->fresh()->agent_id);

        $autre = User::factory()->avecRoles(CodeRole::Observateur)->create();
        $this->post(route('utilisateurs.lier-agent', $autre), ['agent' => '106677'])->assertSessionHasErrors('agent');
        $this->assertNull($autre->fresh()->agent_id);
    }

    private function formulaire(?User $utilisateur = null): Testable
    {
        return Livewire::actingAs($this->admin)->test(FormulaireUtilisateur::class, ['utilisateur' => $utilisateur]);
    }
}
