<?php

namespace Tests\Feature\Http\Comite;

use App\Enums\Acces\CodeRole;
use App\Enums\Editions\StatutEdition;
use App\Enums\Editions\StatutPhase;
use App\Livewire\Editions\FormulaireEdition;
use App\Models\Edition;
use App\Models\Scrutin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
        Livewire::test(FormulaireEdition::class)
            ->set(['form.libelle' => 'Femme modèle du Trésor 2027', 'form.annee' => '2027', 'form.date_debut' => '2027-03-01', 'form.date_fin' => '2027-04-30',
                'form.vote_blanc_autorise' => true, 'form.quorum' => '30', 'nombrePhases' => 3])
            ->call('enregistrer')
            ->assertHasNoErrors()->assertRedirect();

        $edition = Edition::where('annee', 2027)->firstOrFail();
        $this->assertTrue($edition->vote_blanc_autorise);
        $this->get(route('editions.show', $edition))->assertOk()->assertSee('Phase 3 · finale')->assertSee('Lancer l’édition');

        $this->post(route('editions.lancer', $edition))->assertSessionHasErrors(['edition' => 'Toutes les phases doivent être planifiées avant de lancer l’édition.']);

        foreach ([1 => ['2027-03-01', '2027-03-15'], 2 => ['2027-03-20', '2027-04-05'], 3 => ['2027-04-10', '2027-04-30']] as $numero => [$debut, $fin]) {
            $this->put(route('phases.update', $edition->phase($numero)), ['date_debut' => $debut, 'date_fin' => $fin])->assertSessionHasNoErrors();
        }

        $this->from(route('editions.show', $edition))->followingRedirects()->post(route('editions.lancer', $edition))
            ->assertSee('Générez les scrutins de la Phase 1 · primaires avant de lancer l’édition.', false)
            ->assertSee('alerte--fenetre', false);

        Scrutin::factory()->for($edition->phase(1))->create();
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

        $this->actingAs($this->comite)->get(route('editions.create'))->assertOk()->assertSee('Phases et périmètres')
            ->assertSee('Ajouter une phase');
        Livewire::test(FormulaireEdition::class)
            ->assertSet('nombrePhases', 1)->assertSet('form.phases.3.libelle', 'Phase 3 · finale')
            ->set(['form.annee' => '2027', 'form.libelle' => 'Femme modèle du Trésor 2027', 'form.date_debut' => '2027-03-01', 'form.date_fin' => '2027-04-30',
                'form.phases.1.libelle' => 'Élections départementales', 'form.phases.1.colleges.DEPARTEMENTAL' => $college('PAR_CIRCONSCRIPTION'),
                'form.phases.1.colleges.STRUCTURES' => $college('AUCUN'), 'form.phases.1.colleges.DGTCP' => $college('AUCUN'),
                'form.phases.2.libelle' => 'Élections finales', 'form.phases.2.colleges.DEPARTEMENTAL' => $college('ENSEMBLE', 'QUALIFICATION'),
                'form.phases.2.colleges.STRUCTURES' => $college('ENSEMBLE', 'NOMINATION', 3), 'form.phases.2.colleges.DGTCP' => $college('AUCUN'),
                'nombrePhases' => 2])
            ->call('enregistrer')
            ->assertHasNoErrors();

        $edition = Edition::where('annee', 2027)->firstOrFail();
        $this->assertSame([1, 2], $edition->phases->pluck('numero')->all());
        $this->get(route('editions.edit', $edition))->assertOk();
        Livewire::test(FormulaireEdition::class, ['edition' => $edition])->assertSet('nombrePhases', 2);
        $this->get(route('editions.show', $edition))->assertOk()->assertSee('Élections finales');
        $this->get(route('editions.show', [$edition, 'phase' => 2, 'onglet' => 'colleges']))->assertOk()
            ->assertSee('Pôle structures · Un seul scrutin pour tout le périmètre · Nomination par le comité · 3 élue(s) par scrutin');

        Livewire::test(FormulaireEdition::class, ['edition' => $edition])
            ->set(['form.phases.1.colleges' => ['DEPARTEMENTAL' => $college('AUCUN'), 'STRUCTURES' => $college('AUCUN'), 'DGTCP' => $college('ENSEMBLE', 'QUALIFICATION')], 'nombrePhases' => 1])
            ->call('enregistrer')
            ->assertHasErrors('phases')->assertDispatched('formulaire-erreurs')->assertNoRedirect()
            ->set('form.date_fin', '')
            ->call('enregistrer')
            ->assertHasErrors('date_fin')->assertNotDispatched('erreur');
    }

    /**
     * R16 : tant que l'édition n'est pas lancée, la tâche « Ouvrir la phase » le dit et propose de la lancer.
     */
    public function test_r16_la_tache_d_ouverture_propose_de_lancer_l_edition(): void
    {
        $edition = Edition::factory()->avecPhases()->create();
        Scrutin::factory()->for($edition->phase(1))->create(['date_heure_ouverture' => '2030-03-02 07:00:00', 'date_heure_fermeture' => '2030-03-02 17:00:00']);

        $this->actingAs($this->comite)->get(route('editions.show', $edition))->assertOk()
            ->assertSee('Avant d’ouvrir : lancez d’abord l’édition.', false)
            ->assertSee('<button type="button" class="btn btn-secondaire" data-ouvrir="modale-lancer">', false);
    }

    public function test_les_adresses_portent_un_uuid_et_jamais_le_numero_de_la_ligne(): void
    {
        $edition = Edition::factory()->avecPhases()->create();

        $this->assertStringEndsWith('/editions/'.$edition->uuid, route('editions.show', $edition));
        $this->actingAs($this->comite)->get(route('editions.show', $edition))->assertOk();
        $this->get('/editions/'.$edition->id)->assertNotFound();
    }

    public function test_affiche_les_erreurs_de_planification(): void
    {
        $edition = Edition::factory()->avecPhases()->create(['date_debut' => '2030-03-01', 'date_fin' => '2030-03-31', 'date_gel' => '2030-02-15']);

        $this->actingAs($this->comite)->from(route('editions.show', $edition))
            ->followingRedirects()
            ->put(route('phases.update', $edition->phase(1)), ['date_debut' => '2030-05-01', 'date_fin' => '2030-05-10'])
            ->assertSee('La phase doit se dérouler pendant l’édition, du 01/03/2030 au 31/03/2030.', false)
            ->assertSee('signalerErreur', false);
        $this->assertNull($edition->phase(1)->fresh()->date_debut);
    }

    /**
     * R18, R19 : la phase ne s'ouvre qu'avec tous ses scrutins planifiés ; ils se planifient d'un coup, puis la phase
     * s'ouvre et se clôture depuis l'écran de l'édition.
     */
    public function test_r18_ouvre_et_cloture_une_phase(): void
    {
        $edition = Edition::factory()->avecPhases()->enCours()->create();
        $phase = $edition->phase(1);
        $phase->update(['date_debut' => '2026-12-01', 'date_fin' => '2026-12-10']);
        $scrutin = Scrutin::factory()->for($phase)->create(['date_heure_ouverture' => null, 'date_heure_fermeture' => null]);

        $this->actingAs($this->comite)->get(route('editions.show', $edition))->assertOk()
            ->assertSee('0 scrutin(s) planifié(s) sur 1')->assertSee('Planifier tous les scrutins')
            ->assertSee('Avant d’ouvrir : 1 scrutin(s) ne sont pas planifiés.', false)
            ->assertSee('data-ouvrir="modale-ouvrir-'.$phase->id.'" disabled', false);
        $this->post(route('phases.ouvrir', $phase))->assertSessionHasErrors(['phase' => '1 scrutin(s) de la phase ne sont pas planifiés : planifiez-les avant d’ouvrir la phase.']);

        $this->post(route('phases.planifier-scrutins', $phase), ['ouverture' => '2026-12-11T08:00', 'fermeture' => '2026-12-11T18:00'])->assertSessionHasErrors('scrutin');
        $this->post(route('phases.planifier-scrutins', $phase), ['ouverture' => '2026-12-02T08:00', 'fermeture' => '2026-12-02T18:00'])
            ->assertSessionHasNoErrors()->assertSessionHas('succes', '1 scrutin(s) planifié(s) : chacun reste modifiable depuis sa fiche.');
        $this->assertSame('2026-12-02 07:00:00', $scrutin->fresh()->date_heure_ouverture->utc()->format('Y-m-d H:i:s'), 'Heure du Bénin convertie en UTC.');

        $this->post(route('phases.ouvrir', $phase))->assertSessionHasNoErrors();
        $this->assertSame(StatutPhase::Ouverte, $phase->fresh()->statut);

        $this->post(route('phases.cloturer', $phase))->assertSessionHasErrors('phase');
        $this->get(route('editions.show', $edition))->assertOk()
            ->assertSee('0 scrutin(s) terminé(s) sur 1')->assertSee('data-ouvrir="modale-cloturer-'.$phase->id.'" disabled', false)
            ->assertSee($scrutin->libelle);
        $scrutin->update(['statut' => 'CLOS']);
        $this->get(route('editions.show', [$edition, 'phase' => 1, 'onglet' => 'resultats']))->assertSee($scrutin->libelle)->assertSee('Valider');
        $scrutin->update(['statut' => 'RESULTATS_PUBLIES']);
        $this->get(route('editions.show', [$edition, 'phase' => 1, 'onglet' => 'resultats']))->assertSee('Aucun résultat à valider.');
        $this->post(route('phases.cloturer', $phase))->assertSessionHasNoErrors();
        $this->assertSame(StatutPhase::Cloturee, $phase->fresh()->statut);
    }
}
