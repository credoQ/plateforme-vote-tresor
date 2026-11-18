<?php

namespace Tests\Feature\Http\Comite;

use App\Enums\Acces\CodeRole;
use App\Enums\Editions\StatutEdition;
use App\Enums\Editions\StatutPhase;
use App\Models\Edition;
use App\Models\Scrutin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditionsTest extends TestCase
{
    use RefreshDatabase;

    private User $comite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->comite = User::factory()->avecRoles(CodeRole::Comite)->create();
    }

    public function test_seul_le_comite_pilote_les_editions(): void
    {
        $this->actingAs(User::factory()->avecRoles(CodeRole::Administrateur)->create())->get(route('editions.index'))->assertForbidden();
        $this->actingAs($this->comite)->get(route('editions.index'))->assertOk()->assertSee('Aucune édition');
    }

    /**
     * R16, R17 : création d'une édition et de ses trois phases, puis planification et lancement.
     */
    public function test_r17_cree_planifie_et_lance_une_edition(): void
    {
        $this->actingAs($this->comite)->get(route('editions.create'))->assertOk();
        $this->post(route('editions.store'), [
            'annee' => 2027, 'libelle' => 'Femme modèle du Trésor 2027', 'date_debut' => '2027-03-01', 'date_fin' => '2027-04-30',
            'date_gel' => '2027-02-15', 'vote_blanc_autorise' => '1', 'quorum' => '30',
        ])->assertSessionHasNoErrors();

        $edition = Edition::where('annee', 2027)->firstOrFail();
        $this->assertTrue($edition->vote_blanc_autorise);
        $this->get(route('editions.show', $edition))->assertOk()->assertSee('Phase 3 · finale')->assertSee('Lancer l’édition');

        $this->post(route('editions.lancer', $edition))->assertSessionHasErrors('edition');
        $this->get(route('editions.show', $edition))->assertSee('Les trois phases doivent être planifiées');

        foreach ([1 => ['2027-03-01', '2027-03-15'], 2 => ['2027-03-20', '2027-04-05'], 3 => ['2027-04-10', '2027-04-30']] as $numero => [$debut, $fin]) {
            $this->put(route('phases.update', $edition->phase($numero)), ['date_debut' => $debut, 'date_fin' => $fin, 'nb_qualifiees' => 1])->assertSessionHasNoErrors();
        }

        $this->post(route('editions.lancer', $edition))->assertSessionHasNoErrors();
        $this->assertSame(StatutEdition::EnCours, $edition->fresh()->statut);
        $this->get(route('editions.show', $edition))->assertDontSee('Paramétrer')->assertSee('Ouvrir la phase');
    }

    public function test_affiche_les_erreurs_de_planification(): void
    {
        $edition = Edition::factory()->avecPhases()->create(['date_debut' => '2030-03-01', 'date_fin' => '2030-03-31', 'date_gel' => '2030-02-15']);

        $this->actingAs($this->comite)->put(route('phases.update', $edition->phase(1)), ['date_debut' => '2030-05-01', 'date_fin' => '2030-05-10'])
            ->assertSessionHasErrors('date_debut');
    }

    /**
     * R18 : ouverture puis clôture d'une phase depuis l'écran de l'édition.
     */
    public function test_r18_ouvre_et_cloture_une_phase(): void
    {
        $edition = Edition::factory()->avecPhases()->enCours()->create();
        $phase = $edition->phase(1);
        $scrutin = Scrutin::factory()->for($phase)->create();

        $this->actingAs($this->comite)->post(route('phases.ouvrir', $phase))->assertSessionHasNoErrors();
        $this->assertSame(StatutPhase::Ouverte, $phase->fresh()->statut);

        $this->post(route('phases.cloturer', $phase))->assertSessionHasErrors('phase');
        $scrutin->update(['statut' => 'RESULTATS_PUBLIES']);
        $this->post(route('phases.cloturer', $phase))->assertSessionHasNoErrors();
        $this->assertSame(StatutPhase::Cloturee, $phase->fresh()->statut);
    }
}
