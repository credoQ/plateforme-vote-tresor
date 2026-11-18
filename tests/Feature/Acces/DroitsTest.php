<?php

namespace Tests\Feature\Acces;

use App\Actions\Acces\GenererComptesAgents;
use App\Enums\Acces\CodeRole;
use App\Enums\Referentiel\CodeTypeStructure;
use App\Models\Agent;
use App\Models\Candidate;
use App\Models\Inscription;
use App\Models\Scrutin;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DroitsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

    }

    /**
     * R32 : la candidate elle-même, le comité, ou le point focal de sa structure peuvent la désactiver ; personne d'autre.
     */
    public function test_r32_droits_de_desactivation_d_une_candidate(): void
    {
        $dia = $this->structure('DIA');
        $candidate = Candidate::factory()->create();
        Inscription::factory()->create(['scrutin_id' => $candidate->scrutin_id, 'agent_id' => $candidate->agent_id, 'structure_id' => $dia->id]);

        $elle = User::factory()->pourAgent($candidate->agent)->avecRoles(CodeRole::Electeur)->create();
        $comite = User::factory()->avecRoles(CodeRole::Comite)->create();
        $pointFocalDia = User::factory()->pointFocalDe($dia)->create();
        $pointFocalDr = User::factory()->pointFocalDe($this->structure('DR'))->create();
        $autreElecteur = User::factory()->pourAgent(Agent::factory()->create())->avecRoles(CodeRole::Electeur)->create();

        $this->assertTrue($elle->can('desactiver', $candidate));
        $this->assertTrue($comite->can('desactiver', $candidate));
        $this->assertTrue($pointFocalDia->can('reactiver', $candidate));
        $this->assertFalse($pointFocalDr->can('desactiver', $candidate));
        $this->assertFalse($autreElecteur->can('desactiver', $candidate));
    }

    /**
     * R4 : le point focal d'une TD contrôle aussi les inscrits des Trésoreries communales qui lui sont rattachées.
     */
    public function test_r4_le_perimetre_du_point_focal_couvre_ses_structures_rattachees(): void
    {
        $td = $this->structure('TD-ATL');
        $tc = Structure::factory()->deType(CodeTypeStructure::TresorerieCommunale)
            ->create(['departement_id' => $td->departement_id, 'structure_parente_id' => $td->id]);
        $pointFocal = User::factory()->pointFocalDe($td)->create();
        $scrutin = Scrutin::factory()->create();

        $inscritTc = Inscription::factory()->create(['scrutin_id' => $scrutin->id, 'structure_id' => $tc->id]);
        $inscritDia = Inscription::factory()->create(['scrutin_id' => $scrutin->id, 'structure_id' => $this->structure('DIA')->id]);

        $this->assertEqualsCanonicalizing([$td->id, $tc->id], $pointFocal->perimetre());
        $this->assertTrue($pointFocal->can('view', $inscritTc));
        $this->assertFalse($pointFocal->can('view', $inscritDia));
    }

    public function test_les_profils_ouvrent_leurs_espaces(): void
    {
        $admin = User::factory()->avecRoles(CodeRole::Administrateur)->create();
        $observateur = User::factory()->avecRoles(CodeRole::Observateur)->create();
        $electeur = User::factory()->pourAgent(Agent::factory()->create())->avecRoles(CodeRole::Electeur)->create();

        $this->assertTrue($admin->can('administrer'));
        $this->assertFalse($admin->can('piloter'));
        $this->assertTrue($observateur->can('observer'));
        $this->assertFalse($observateur->can('administrer'));
        $this->assertTrue($electeur->can('voter'));
        $this->assertFalse(User::factory()->avecRoles(CodeRole::Electeur)->create()->can('voter'), 'Un électeur sans agent ne vote pas.');
    }

    /**
     * R3, R13 : un compte électeur par agent en service, identifiant = matricule ; aucun doublon à la relance.
     */
    public function test_r3_genere_un_compte_par_agent_en_service(): void
    {
        $enService = Agent::factory()->affecteA($this->structure('DIA'))->create(['matricule' => '300001']);
        Agent::factory()->create(['matricule' => '300002']);

        $sansEmail = Agent::factory()->affecteA($this->structure('DIA'))->create(['matricule' => '300003', 'email' => null]);
        $enService->update(['email' => 'agent@example.test']);

        $resultat = app(GenererComptesAgents::class)->handle();

        $this->assertSame(2, $resultat['crees']);
        $this->assertSame(['300003'], array_column($resultat['sans_email'], 'matricule'));
        $compte = $enService->compte()->firstOrFail();
        $this->assertSame('300001', $compte->login);
        $this->assertSame('agent@example.test', $compte->adresseDeConnexion());
        $this->assertTrue($compte->can('voter'));
        $this->assertNotNull($sansEmail->compte);

        $this->assertSame(0, app(GenererComptesAgents::class)->handle()['crees']);
        $this->artisan('acces:generer-comptes')->expectsOutputToContain('déjà un compte')->expectsOutputToContain('sans adresse e-mail')->assertSuccessful();
    }

    public function test_cree_un_administrateur_en_ligne_de_commande(): void
    {
        $this->artisan('acces:creer-administrateur', ['login' => 'admin', 'nom' => 'Administrateur', 'email' => 'admin@example.test'])
            ->expectsOutputToContain('admin@example.test')
            ->assertSuccessful();

        $this->assertTrue(User::where('login', 'admin')->firstOrFail()->can('administrer'));
    }

    private function structure(string $code): Structure
    {
        return Structure::where('code', $code)->firstOrFail();
    }
}
