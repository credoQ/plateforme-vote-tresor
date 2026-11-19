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
     * R19, R27 : planification, actualisation puis arrêt des listes ; les listes arrêtées ne s'actualisent plus.
     */
    public function test_r27_planifie_et_arrete_les_listes_d_un_scrutin(): void
    {
        $scrutin = $this->scrutinDia();

        $this->actingAs($this->comite)->get(route('scrutins.show', $scrutin))->assertOk()->assertSee('AHOUANSOU')->assertSee('Arrêter les listes');

        $this->post(route('scrutins.planifier', $scrutin), ['ouverture' => '2026-11-20T08:00', 'fermeture' => '2026-11-20T18:00'])->assertSessionHasNoErrors();
        $this->assertSame('2026-11-20 07:00:00', $scrutin->fresh()->date_heure_ouverture->utc()->format('Y-m-d H:i:s'), 'Heure du Bénin convertie en UTC.');

        $this->post(route('scrutins.actualiser', $scrutin))->assertSessionHas('succes');
        $this->post(route('scrutins.arreter', $scrutin))->assertSessionHasNoErrors();
        $this->assertSame(StatutScrutin::ListesArretees, $scrutin->fresh()->statut);

        $this->post(route('scrutins.actualiser', $scrutin))->assertSessionHasErrors('scrutin');
        $this->get(route('scrutins.show', $scrutin))->assertDontSee('Actualiser les listes');
    }

    /**
     * R27 : le comité rouvre des listes arrêtées par une décision motivée, puis les arrête de nouveau ; le point focal ne le peut pas.
     */
    public function test_r27_le_comite_rouvre_les_listes_arretees_avec_un_motif(): void
    {
        $scrutin = $this->scrutinDia();
        $pointFocal = User::factory()->pointFocalDe(Structure::where('code', 'DIA')->firstOrFail())->create();
        $this->actingAs($this->comite)->post(route('scrutins.arreter', $scrutin))->assertSessionHasNoErrors();

        $this->get(route('scrutins.show', $scrutin))->assertSee('Rouvrir les listes');
        $this->post(route('scrutins.rouvrir', $scrutin), ['motif' => ''])->assertSessionHasErrors('motif');
        $this->actingAs($pointFocal)->post(route('scrutins.rouvrir', $scrutin), ['motif' => 'Agente omise'])->assertForbidden();
        $this->assertSame(StatutScrutin::ListesArretees, $scrutin->fresh()->statut);

        $this->actingAs($this->comite)->post(route('scrutins.rouvrir', $scrutin), ['motif' => 'Agente omise de l’import RH'])->assertSessionHas('succes');
        $this->assertSame(StatutScrutin::EnPreparation, $scrutin->fresh()->statut);
        $this->get(route('scrutins.show', $scrutin))->assertSee('Actualiser les listes')->assertSee('Arrêter les listes');
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
        $this->post(route('scrutins.arreter', $scrutin))->assertForbidden();
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
            ->assertSee('Nominer la candidate')->assertSee('Nomination par le comité · 1 élue(s)')->assertSee($this->femmeDia->matricule);

        $this->post(route('nominations.store', $scrutin), ['matricule' => $this->femmeDia->matricule, 'reference_nomination' => ''])->assertSessionHasErrors('reference_nomination');
        $this->post(route('nominations.store', $scrutin), ['matricule' => $this->femmeDia->matricule, 'reference_nomination' => 'PV du CODIR du 25/11/2026'])
            ->assertSessionHas('succes');
        $this->get(route('scrutins.show', $scrutin))->assertSee('AHOUANSOU')->assertSee('PV du CODIR du 25/11/2026')->assertSee('Retirer la nomination');

        $candidate = $scrutin->candidates()->sole();
        $this->actingAs($pointFocal)->post(route('nominations.store', $scrutin), ['matricule' => '000000', 'reference_nomination' => 'PV'])->assertForbidden();
        $this->post(route('nominations.destroy', $candidate))->assertForbidden();

        $this->actingAs($this->comite)->post(route('nominations.destroy', $candidate))->assertSessionHas('succes');
        $this->assertSame(0, $scrutin->candidates()->count());
    }

    private function scrutinDia(): Scrutin
    {
        if (Scrutin::count() === 0) {
            app(GenererScrutins::class)->handle($this->edition->phase(Phase::PRIMAIRES));
        }

        return Scrutin::where('code', 'STR-DIA')->firstOrFail();
    }
}
