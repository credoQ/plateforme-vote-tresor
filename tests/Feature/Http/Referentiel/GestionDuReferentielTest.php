<?php

namespace Tests\Feature\Http\Referentiel;

use App\Enums\Acces\CodeRole;
use App\Enums\Audit\ActionAuditee;
use App\Enums\Referentiel\CodeTypeStructure;
use App\Models\Agent;
use App\Models\Departement;
use App\Models\JournalAudit;
use App\Models\Structure;
use App\Models\TypeStructure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GestionDuReferentielTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-11-19 10:00:00'));
        $this->admin = User::factory()->avecRoles(CodeRole::Administrateur)->create();
    }

    /**
     * R5, R8, R9 : création d'une Trésorerie communale rattachée à la TD de son département, tracée au journal.
     */
    public function test_r8_cree_une_tresorerie_communale_depuis_l_ecran(): void
    {
        $td = $this->structure('TD-ATL');

        $this->actingAs($this->admin)->get(route('structures.create'))->assertOk()->assertSee('Enregistrer la structure');
        $this->post(route('structures.store'), [
            'code' => 'tc-test', 'sigle' => '', 'libelle' => 'Trésorerie communale de test', 'niveau' => '1',
            'type_structure_id' => (string) $this->type(CodeTypeStructure::TresorerieCommunale),
            'departement_id' => (string) $td->departement_id, 'structure_parente_id' => (string) $td->id,
        ])->assertSessionHasNoErrors()->assertRedirect(route('structures.show', $this->structure('TC-TEST')));

        $tc = $this->structure('TC-TEST');
        $this->assertNull($tc->sigle);
        $this->assertTrue($tc->structureParente->is($td));
        $this->assertSame(['code' => 'TC-TEST', 'creation' => true], JournalAudit::where('action', ActionAuditee::StructureEnregistree->value)->sole()->details);

        $this->get(route('structures.show', $td))->assertOk()->assertSee('TC-TEST');
    }

    /**
     * R9 : le message de la règle parvient à l'administrateur et rien n'est enregistré.
     */
    public function test_r9_refuse_une_td_sans_departement(): void
    {
        $this->actingAs($this->admin)->post(route('structures.store'), [
            'code' => 'TD-TEST', 'libelle' => 'TD de test', 'niveau' => '1',
            'type_structure_id' => (string) $this->type(CodeTypeStructure::TresorerieDepartementale),
        ])->assertSessionHasErrors(['departement_id' => 'Une Trésorerie départementale ou communale doit être située dans un département.']);

        $this->assertDatabaseMissing('structures', ['code' => 'TD-TEST']);
        $this->assertSame(0, JournalAudit::count());
    }

    /**
     * R8 : rattacher une structure à l'une de ses structures rattachées créerait une boucle.
     */
    public function test_r8_refuse_un_rattachement_en_boucle(): void
    {
        $cabinet = $this->structure('CAB');
        $cellule = Structure::factory()->deType(CodeTypeStructure::Cellule)->create(['code' => 'CELL-TEST', 'structure_parente_id' => $cabinet->id]);

        $this->actingAs($this->admin)->put(route('structures.update', $cabinet), [
            'code' => $cabinet->code, 'libelle' => $cabinet->libelle, 'niveau' => '1',
            'type_structure_id' => (string) $cabinet->type_structure_id, 'structure_parente_id' => (string) $cellule->id,
        ])->assertSessionHasErrors(['structure_parente_id' => 'Une structure ne peut pas être rattachée à l’une de ses structures rattachées.']);

        $this->assertNull($cabinet->fresh()->structure_parente_id);
    }

    public function test_modifie_le_libelle_d_une_structure(): void
    {
        $dmg = $this->structure('DMG');

        $this->actingAs($this->admin)->get(route('structures.edit', $dmg))->assertOk();
        $this->put(route('structures.update', $dmg), [
            'code' => 'DMG', 'sigle' => 'DMG', 'libelle' => 'Direction des Moyens généraux et du Patrimoine', 'niveau' => '1',
            'type_structure_id' => (string) $dmg->type_structure_id,
        ])->assertSessionHasNoErrors()->assertRedirect(route('structures.show', $dmg));

        $this->assertSame('Direction des Moyens généraux et du Patrimoine', $dmg->fresh()->libelle);
        $this->assertSame(['libelle', 'updated_at'], JournalAudit::where('action', ActionAuditee::StructureEnregistree->value)->sole()->details['modifications']);
    }

    /**
     * R5 : désactivation refusée tant qu'un agent y est en service, puis désactivation et réactivation tracées.
     */
    public function test_r5_desactive_puis_reactive_une_structure(): void
    {
        $dia = $this->structure('DIA');
        $agent = Agent::factory()->affecteA($dia, Carbon::parse('2020-01-01'))->create();

        $this->actingAs($this->admin)->post(route('structures.desactiver', $dia))
            ->assertSessionHasErrors(['structure' => 'La structure compte 1 agent(s) en service : mutez-les ou mettez fin à leur affectation avant de la désactiver.']);
        $this->assertTrue($dia->fresh()->actif);

        $agent->affectations()->update(['date_fin' => '2026-11-18']);
        $this->post(route('structures.desactiver', $dia), ['motif' => 'Fusion avec la DGTCP'])->assertSessionHasNoErrors();
        $this->assertFalse($dia->fresh()->actif);
        $this->assertSame('Fusion avec la DGTCP', JournalAudit::where('action', ActionAuditee::StructureDesactivee->value)->sole()->details['motif']);

        $this->get(route('structures.index'))->assertDontSee("Direction de l'Informatique et des Archives");
        $this->get(route('structures.index', ['statut' => 'desactivees']))->assertSee('DIA');

        $this->post(route('structures.reactiver', $dia))->assertSessionHasNoErrors();
        $this->assertTrue($dia->fresh()->actif);
        $this->assertSame(1, JournalAudit::where('action', ActionAuditee::StructureReactivee->value)->count());
    }

    public function test_refuse_de_desactiver_une_structure_dont_un_service_est_actif(): void
    {
        $dia = $this->structure('DIA');
        Structure::factory()->serviceDe($dia)->create();

        $this->actingAs($this->admin)->post(route('structures.desactiver', $dia))
            ->assertSessionHasErrors(['structure' => 'Des structures rattachées sont encore actives : désactivez-les ou rattachez-les ailleurs d’abord.']);
        $this->assertTrue($dia->fresh()->actif);
    }

    public function test_refuse_de_reactiver_un_service_dont_la_structure_mere_est_desactivee(): void
    {
        $mere = Structure::factory()->deType(CodeTypeStructure::DirectionCentrale)->inactive()->create();
        $service = Structure::factory()->serviceDe($mere)->inactive()->create();

        $this->actingAs($this->admin)->post(route('structures.reactiver', $service))
            ->assertSessionHasErrors(['structure' => 'La structure de rattachement est désactivée : réactivez-la d’abord.']);
        $this->assertFalse($service->fresh()->actif);
    }

    /**
     * R7, R9 : types classés par pôle et départements avec leur Trésorerie départementale.
     */
    public function test_r7_affiche_les_types_par_pole_et_les_departements(): void
    {
        $this->actingAs($this->admin)->get(route('structures.organisation'))->assertOk()
            ->assertSeeInOrder(['Pôle départemental', 'Trésorerie communale'])
            ->assertSee('Agence comptable de la Dette')
            ->assertSee(Departement::where('code', 'ZOU')->value('libelle'))
            ->assertSee('TD-ZOU');
    }

    public function test_filtre_les_types_par_pole_et_les_departements_sans_perdre_l_autre_filtre(): void
    {
        $this->actingAs($this->admin)->get(route('structures.organisation', ['pole' => 'STRUCTURES', 'departement' => 'zou']))->assertOk()
            ->assertSee('Agence comptable de la Dette')->assertDontSee('Trésorerie communale')
            ->assertSee('TD-ZOU')->assertDontSee('TD-ATL')
            ->assertSee('<input type="hidden" name="departement" value="zou">', false)
            ->assertSee('<input type="hidden" name="pole" value="STRUCTURES">', false);
    }

    public function test_filtre_les_structures_rattachees_d_une_structure(): void
    {
        $td = $this->structure('TD-ATL');

        $this->actingAs($this->admin)->get(route('structures.show', [$td, 'recherche' => 'tc-ouidah']))
            ->assertOk()->assertSee('TC-OUIDAH')->assertSee('<b class="chiffres">1</b> structure(s)', false);
        $this->get(route('structures.show', [$td, 'statut' => 'desactivees']))
            ->assertSee('Aucune structure rattachée ne correspond aux filtres.');
    }

    public function test_filtre_les_structures_par_type_et_departement(): void
    {
        $zou = Departement::where('code', 'ZOU')->firstOrFail();

        $this->actingAs($this->admin)->get(route('structures.index', ['type' => $this->type(CodeTypeStructure::TresorerieDepartementale)]))
            ->assertOk()->assertSee('<b class="chiffres">12</b> structure(s)', false)->assertSee('TD-ALI')
            ->assertDontSee('<td class="reference">DIA</td>', false);
        $this->get(route('structures.index', ['departement' => $zou->id]))->assertSee('TD-ZOU')->assertDontSee('TD-ATL');
        $this->get(route('structures.index', ['recherche' => 'td-zou']))->assertSee('TD-ZOU')->assertDontSee('TD-ATL');
    }

    /**
     * Le comité consulte le référentiel sans pouvoir le modifier ; le point focal n'y a pas accès.
     */
    public function test_le_comite_consulte_en_lecture_seule_et_le_point_focal_est_refuse(): void
    {
        $dia = $this->structure('DIA');
        $comite = User::factory()->avecRoles(CodeRole::Comite)->create();

        $this->actingAs($comite)->get(route('structures.show', $dia))->assertOk()->assertDontSee('Désactiver la structure')->assertDontSee('Créer une structure');
        $this->get(route('structures.create'))->assertForbidden();
        $this->post(route('structures.desactiver', $dia))->assertForbidden();
        $this->assertTrue($dia->fresh()->actif);

        $this->actingAs(User::factory()->pointFocalDe($dia)->create())->get(route('structures.index'))->assertForbidden();
    }

    private function structure(string $code): Structure
    {
        return Structure::where('code', $code)->firstOrFail();
    }

    private function type(CodeTypeStructure $type): int
    {
        return TypeStructure::where('code', $type->value)->value('id');
    }
}
