<?php

namespace Tests\Feature\Http\Comite;

use App\Actions\Scrutins\GenererScrutins;
use App\Enums\Acces\CodeRole;
use App\Enums\Scrutins\OrigineCandidate;
use App\Enums\Scrutins\StatutCandidate;
use App\Enums\Scrutins\StatutScrutin;
use App\Models\Agent;
use App\Models\Edition;
use App\Models\Phase;
use App\Models\Scrutin;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ScrutinsTest extends TestCase
{
    use RefreshDatabase;

    private User $comite;

    private Edition $edition;

    private Agent $femmeDia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-11-18 09:00:00'));
        $this->comite = User::factory()->avecRoles(CodeRole::Comite)->create();
        $this->edition = Edition::factory()->avecPhases()->enCours()->create([
            'annee' => 2026, 'date_debut' => '2026-11-01', 'date_fin' => '2026-12-31', 'date_gel' => '2026-10-01',
        ]);
        $depuis = Carbon::parse('2020-01-01');
        $this->femmeDia = Agent::factory()->femme()->affecteA(Structure::where('code', 'DIA')->firstOrFail(), $depuis)->create(['nom' => 'AHOUANSOU']);
        Agent::factory()->femme()->affecteA(Structure::where('code', 'DR')->firstOrFail(), $depuis)->create(['nom' => 'KPADE']);
    }

    /**
     * R20, R37 : génération des scrutins depuis l'écran de l'édition, avec les circonscriptions écartées signalées.
     */
    public function test_r20_genere_les_scrutins_d_une_phase_depuis_l_edition(): void
    {
        $this->actingAs($this->comite)->post(route('phases.scrutins.generer', $this->edition->phase(Phase::PRIMAIRES)))
            ->assertSessionHasNoErrors()->assertSessionHas('sans_femme');

        $this->followingRedirects()->get(route('editions.show', $this->edition))
            ->assertSee('Scrutin de structure · Direction de l&#039;Informatique et des Archives', false)
            ->assertSee('À planifier');
        $this->assertSame(2, Scrutin::count());
    }

    /**
     * R19 : un scrutin planifié hors des dates de sa phase est refusé, avec le message affiché sur la fiche.
     */
    public function test_r19_affiche_l_erreur_d_un_scrutin_planifie_hors_de_sa_phase(): void
    {
        $scrutin = $this->scrutinDia();
        $scrutin->phase->update(['date_debut' => '2026-11-20', 'date_fin' => '2026-11-30']);

        $this->actingAs($this->comite)->from(route('scrutins.show', $scrutin))
            ->followingRedirects()
            ->post(route('scrutins.planifier', $scrutin), ['ouverture' => '2026-11-16T09:44', 'fermeture' => '2026-11-24T09:44'])
            ->assertSee('Le scrutin ne peut pas s’ouvrir avant le début de la phase, le 20/11/2026.', false)
            ->assertSee('signalerErreur', false);
        $this->assertNull($scrutin->fresh()->date_heure_ouverture);
    }

    /**
     * R19, R27 : planification puis arrêt des listes de la phase ; les listes arrêtées ne se mettent plus à jour.
     */
    public function test_r27_planifie_et_arrete_les_listes_d_un_scrutin(): void
    {
        $scrutin = $this->scrutinDia();

        $this->actingAs($this->comite)->get(route('scrutins.show', $scrutin))->assertOk()->assertSee('AHOUANSOU')->assertSee('Arrêter les listes');

        $this->post(route('scrutins.planifier', $scrutin), ['ouverture' => '2026-11-20T08:00', 'fermeture' => '2026-11-20T18:00'])->assertSessionHasNoErrors();
        $this->assertSame('2026-11-20 07:00:00', $scrutin->fresh()->date_heure_ouverture->utc()->format('Y-m-d H:i:s'), 'Heure du Bénin convertie en UTC.');

        $this->get(route('scrutins.show', $scrutin))->assertSee('Elles se mettent à jour automatiquement', false)->assertDontSee('Actualiser les listes');
        $this->post(route('phases.arreter-listes', $scrutin->phase))->assertSessionHasNoErrors();
        $this->assertSame(StatutScrutin::ListesArretees, $scrutin->fresh()->statut);

        $this->get(route('scrutins.show', $scrutin))->assertDontSee('Elles se mettent à jour automatiquement', false);
    }

    /**
     * R19, R27 : la fiche suit le déroulement du scrutin (étape courante, rappel des étapes faites) et répartit
     * son contenu en onglets.
     */
    public function test_r27_la_fiche_presente_l_etape_courante_et_ses_onglets(): void
    {
        $scrutin = $this->scrutinDia();

        $this->actingAs($this->comite)->get(route('scrutins.show', $scrutin))->assertOk()
            ->assertSee('Arrêter les listes électorales')->assertDontSee('Arrêter les listes de la phase')->assertSee('AHOUANSOU');

        $this->post(route('phases.arreter-listes', $scrutin->phase))->assertSessionHasNoErrors();
        $this->get(route('scrutins.show', $scrutin))
            ->assertSee('Quand le vote s’ouvre-t-il et se ferme-t-il ?', false)->assertSee('Enregistrer les horaires')->assertSee('Rouvrir les listes');

        $this->post(route('scrutins.planifier', $scrutin), ['ouverture' => '2026-11-20T08:00', 'fermeture' => '2026-11-20T18:00'])->assertSessionHasNoErrors();
        $this->get(route('scrutins.show', $scrutin))->assertSee('Le scrutin s’ouvrira seul le 20/11/2026 à 08:00.', false);

        $this->get(route('scrutins.show', [$scrutin, 'onglet' => 'liste']))->assertSee($this->femmeDia->matricule);
        $this->get(route('scrutins.show', [$scrutin, 'onglet' => 'resultats']))->assertSee('Les résultats seront disponibles après le dépouillement.');
    }

    /**
     * R27 : un arrêt refusé pour toute la phase s'affiche sur la phase (détail des scrutins) et en fenêtre d'erreur.
     */
    public function test_r27_un_arret_refuse_s_affiche_sur_la_phase_et_en_fenetre_d_erreur(): void
    {
        $scrutin = $this->scrutinDia();
        $this->femmeDia->affectations()->update(['date_fin' => '2026-11-17']);
        $this->edition->update(['date_gel' => null]);

        $this->actingAs($this->comite)->followingRedirects()->post(route('phases.arreter-listes', $scrutin->phase))
            ->assertSee('Aucune liste n’est arrêtée : 1 scrutin(s) à compléter.', false)
            ->assertSee('liste électorale vide', false)
            ->assertSee('signalerErreur', false);
        $this->assertSame(StatutScrutin::EnPreparation, $scrutin->fresh()->statut);
    }

    /**
     * R27 : le comité rouvre des listes arrêtées par une décision motivée, puis les arrête de nouveau ; le point focal ne le peut pas.
     */
    public function test_r27_le_comite_rouvre_les_listes_arretees_avec_un_motif(): void
    {
        $scrutin = $this->scrutinDia();
        $pointFocal = User::factory()->pointFocalDe(Structure::where('code', 'DIA')->firstOrFail())->create();
        $this->actingAs($this->comite)->post(route('phases.arreter-listes', $scrutin->phase))->assertSessionHasNoErrors();

        $this->get(route('scrutins.show', $scrutin))->assertSee('Rouvrir les listes');
        $this->post(route('scrutins.rouvrir', $scrutin), ['motif' => ''])->assertSessionHasErrors('motif');
        $this->actingAs($pointFocal)->post(route('scrutins.rouvrir', $scrutin), ['motif' => 'Agente omise'])->assertForbidden();
        $this->assertSame(StatutScrutin::ListesArretees, $scrutin->fresh()->statut);

        $this->actingAs($this->comite)->post(route('scrutins.rouvrir', $scrutin), ['motif' => 'Agente omise de l’import RH'])->assertSessionHas('succes');
        $this->assertSame(StatutScrutin::EnPreparation, $scrutin->fresh()->statut);
        $this->get(route('scrutins.show', $scrutin))->assertSee('Elles se mettent à jour automatiquement', false)->assertSee('Arrêter les listes');
    }

    /**
     * R32, R4 : le point focal désactive une candidate de sa structure, et ne voit pas les autres structures.
     */
    public function test_r32_le_point_focal_desactive_une_candidate_de_sa_structure(): void
    {
        $scrutin = $this->scrutinDia();
        $pointFocal = User::factory()->pointFocalDe(Structure::where('code', 'DIA')->firstOrFail())->create();
        $candidate = $scrutin->candidates()->sole();

        $this->actingAs($pointFocal)->get(route('scrutins.show', $scrutin))->assertOk()->assertSee('Désactiver')->assertDontSee('Arrêter les listes');
        $this->post(route('candidatures.desister', $candidate), ['motif' => 'Demande écrite'])->assertSessionHas('succes');
        $this->assertSame(StatutCandidate::Desactivee, $candidate->fresh()->statut);

        $scrutinDr = Scrutin::where('code', 'STR-DR')->firstOrFail();
        $this->get(route('scrutins.show', $scrutinDr))->assertOk()->assertDontSee('KPADE');
        $this->post(route('phases.arreter-listes', $scrutin->phase))->assertForbidden();
    }

    public function test_un_electeur_n_accede_pas_aux_scrutins(): void
    {
        $electeur = User::factory()->pourAgent($this->femmeDia)->avecRoles(CodeRole::Electeur)->create();

        $this->actingAs($electeur)->get(route('scrutins.show', $this->scrutinDia()))->assertForbidden();
    }

    /**
     * R58 : le comité nomine une candidate depuis la fiche d'un scrutin à nomination, puis peut retirer la nomination ; le point focal ne le peut pas.
     */
    public function test_r58_le_comite_nomine_une_candidate_depuis_la_fiche_du_scrutin(): void
    {
        $this->edition->phase(Phase::PRIMAIRES)->colleges()->update(['origine_candidates' => OrigineCandidate::Nomination]);
        $scrutin = $this->scrutinDia();
        $pointFocal = User::factory()->pointFocalDe(Structure::where('code', 'DIA')->firstOrFail())->create();

        $this->actingAs($this->comite)->get(route('scrutins.show', $scrutin))->assertOk()
            ->assertSee('Nominer les candidates')->assertSee('Nomination par le comité · 1 élue(s)')->assertSee($this->femmeDia->matricule);

        $this->post(route('nominations.store', $scrutin), ['matricules' => [$this->femmeDia->matricule], 'reference_nomination' => ''])->assertSessionHasErrors('reference_nomination');
        $this->post(route('nominations.store', $scrutin), ['matricules' => [$this->femmeDia->matricule], 'reference_nomination' => 'PV du CODIR du 25/11/2026'])
            ->assertSessionHas('succes');
        $this->get(route('scrutins.show', $scrutin))->assertSee('AHOUANSOU')->assertSee('PV du CODIR du 25/11/2026')->assertSee('Retirer la nomination');

        $candidate = $scrutin->candidates()->sole();
        $this->actingAs($pointFocal)->post(route('nominations.store', $scrutin), ['matricules' => ['000000'], 'reference_nomination' => 'PV'])->assertForbidden();
        $this->post(route('nominations.destroy', $candidate))->assertForbidden();

        $this->actingAs($this->comite)->post(route('nominations.destroy', $candidate))->assertSessionHas('succes');
        $this->assertSame(0, $scrutin->candidates()->count());
    }

    public function test_filtre_les_candidates_d_un_scrutin_sans_perdre_la_recherche_de_la_liste_electorale(): void
    {
        $scrutin = $this->scrutinDia();

        $this->actingAs($this->comite)->get(route('scrutins.show', [$scrutin, 'candidate' => 'ahouansou']))
            ->assertOk()->assertSee('<b class="chiffres">1</b> candidate(s)', false);
        $this->get(route('scrutins.show', [$scrutin, 'candidate' => 'inconnue', 'recherche' => 'ahouansou']))
            ->assertSee('Aucune candidate ne correspond aux filtres.');
        $this->get(route('scrutins.show', [$scrutin, 'statut_candidate' => 'DESACTIVEE']))
            ->assertSee('Aucune candidate ne correspond aux filtres.');
    }

    /**
     * R58 : une même décision nomine plusieurs candidates d'un coup ; si l'une est refusée, aucune n'est nominée.
     */
    public function test_r58_nomine_plusieurs_candidates_ou_aucune(): void
    {
        $this->edition->phase(Phase::PRIMAIRES)->colleges()->update(['origine_candidates' => OrigineCandidate::Nomination]);
        $seconde = Agent::factory()->femme()->affecteA(Structure::where('code', 'DIA')->firstOrFail(), Carbon::parse('2020-01-01'))->create(['nom' => 'DOSSOU']);
        $scrutin = $this->scrutinDia();
        $this->actingAs($this->comite);

        $this->post(route('nominations.store', $scrutin), ['matricules' => [], 'reference_nomination' => 'PV'])->assertSessionHasErrors('matricules');
        $this->post(route('nominations.store', $scrutin), ['matricules' => [$this->femmeDia->matricule, '000000'], 'reference_nomination' => 'PV'])->assertSessionHasErrors('matricule');
        $this->assertSame(0, $scrutin->candidates()->count());

        $this->post(route('nominations.store', $scrutin), ['matricules' => [$this->femmeDia->matricule, $seconde->matricule], 'reference_nomination' => 'PV du CODIR du 25/11/2026'])
            ->assertSessionHas('succes', '2 candidates sont nominées.');
        $this->assertSame(2, $scrutin->candidates()->count());
    }

    private function scrutinDia(): Scrutin
    {
        if (Scrutin::count() === 0) {
            app(GenererScrutins::class)->handle($this->edition->phase(Phase::PRIMAIRES));
        }

        return Scrutin::where('code', 'STR-DIA')->firstOrFail();
    }
}
