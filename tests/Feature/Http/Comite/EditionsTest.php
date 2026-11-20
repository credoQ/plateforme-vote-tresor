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

    public function test_filtre_les_editions_par_statut_et_par_annee(): void
    {
        Edition::factory()->create(['annee' => 2031, 'libelle' => 'Édition préparée']);
        Edition::factory()->enCours()->create(['annee' => 2032, 'libelle' => 'Édition lancée']);

        $this->actingAs($this->comite)->get(route('editions.index', ['statut' => StatutEdition::EnCours->value]))
            ->assertOk()->assertSee('Édition lancée')->assertDontSee('Édition préparée')->assertSee('Réinitialiser');
        $this->get(route('editions.index', ['recherche' => '2031']))->assertSee('Édition préparée')->assertDontSee('Édition lancée');
        $this->get(route('editions.index'))->assertSee('Affichage de')->assertDontSee('Réinitialiser');
    }

    /**
     * R16, R17 : création d'une édition avec l'organisation par défaut, puis planification et lancement.
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
        $this->get(route('editions.show', $edition))->assertSee('Toutes les phases doivent être planifiées');

        foreach ([1 => ['2027-03-01', '2027-03-15'], 2 => ['2027-03-20', '2027-04-05'], 3 => ['2027-04-10', '2027-04-30']] as $numero => [$debut, $fin]) {
            $this->put(route('phases.update', $edition->phase($numero)), ['date_debut' => $debut, 'date_fin' => $fin])->assertSessionHasNoErrors();
        }

        $this->post(route('editions.lancer', $edition))->assertSessionHasNoErrors();
        $this->assertSame(StatutEdition::EnCours, $edition->fresh()->statut);
        $this->get(route('editions.show', $edition))->assertDontSee('Paramétrer')->assertSee('Ouvrir la phase');
    }

    /**
     * R17, R20, R28, R52 : le formulaire paramètre les phases et leurs collèges ; une phase retirée et un collège sans scrutin sont ignorés.
     */
    public function test_r17_cree_une_edition_en_deux_phases_depuis_le_formulaire(): void
    {
        $college = fn (string $decoupage, string $origine = 'NOMINATION', int $elues = 1): array => ['decoupage' => $decoupage, 'origine' => $origine, 'nb_elues' => $elues];

        $this->actingAs($this->comite)->get(route('editions.create'))->assertOk()->assertSee('Phases et collèges')->assertSee('Phase 3 · finale', false)
            ->assertSee('Ajouter une phase')->assertViewHas('phasesAffichees', 1);
        $this->post(route('editions.store'), [
            'annee' => 2027, 'libelle' => 'Femme modèle du Trésor 2027', 'date_debut' => '2027-03-01', 'date_fin' => '2027-04-30', 'date_gel' => '2027-02-15',
            'phases' => [
                1 => ['libelle' => 'Élections départementales', 'colleges' => ['DEPARTEMENTAL' => $college('PAR_CIRCONSCRIPTION'), 'STRUCTURES' => $college('AUCUN'), 'DGTCP' => $college('AUCUN')]],
                2 => ['active' => '1', 'libelle' => 'Élections finales', 'colleges' => ['DEPARTEMENTAL' => $college('ENSEMBLE', 'QUALIFICATION'), 'STRUCTURES' => $college('ENSEMBLE', 'NOMINATION', 3), 'DGTCP' => $college('AUCUN')]],
                3 => ['libelle' => 'Phase 3', 'colleges' => ['DGTCP' => $college('ENSEMBLE', 'QUALIFICATION')]],
            ],
        ])->assertSessionHasNoErrors();

        $edition = Edition::where('annee', 2027)->firstOrFail();
        $this->assertSame([1, 2], $edition->phases->pluck('numero')->all());
        $this->get(route('editions.edit', $edition))->assertOk()->assertViewHas('phasesAffichees', 2);
        $this->get(route('editions.show', $edition))->assertOk()->assertSee('Élections finales');
        $this->get(route('editions.show', [$edition, 'phase' => 2, 'onglet' => 'colleges']))->assertOk()
            ->assertSee('Pôle structures · Un seul scrutin · Nomination par le comité · 3 élue(s) par scrutin');

        $this->put(route('editions.update', $edition), [
            'annee' => 2027, 'libelle' => 'Femme modèle du Trésor 2027', 'date_debut' => '2027-03-01', 'date_fin' => '2027-04-30', 'date_gel' => '2027-02-15',
            'phases' => [1 => ['libelle' => 'Phase 1', 'colleges' => ['DGTCP' => $college('ENSEMBLE', 'QUALIFICATION')]]],
        ])->assertSessionHasErrors('phases');
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
        $this->get(route('editions.show', $edition))->assertOk()
            ->assertSee('0 scrutin(s) terminé(s) sur 1')->assertSee('title="Tous les résultats doivent être publiés"', false)
            ->assertSee($scrutin->libelle);
        $scrutin->update(['statut' => 'CLOS']);
        $this->get(route('editions.show', [$edition, 'phase' => 1, 'onglet' => 'resultats']))->assertSee($scrutin->libelle)->assertSee('Valider');
        $scrutin->update(['statut' => 'RESULTATS_PUBLIES']);
        $this->get(route('editions.show', [$edition, 'phase' => 1, 'onglet' => 'resultats']))->assertSee('Aucun résultat à valider.');
        $this->post(route('phases.cloturer', $phase))->assertSessionHasNoErrors();
        $this->assertSame(StatutPhase::Cloturee, $phase->fresh()->statut);
    }
}
