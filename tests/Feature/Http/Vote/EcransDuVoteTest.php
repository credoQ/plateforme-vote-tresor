<?php

namespace Tests\Feature\Http\Vote;

use App\Actions\Scrutins\ArreterListes;
use App\Actions\Scrutins\GenererScrutins;
use App\Actions\Scrutins\OuvrirScrutin;
use App\Actions\Scrutins\PlanifierScrutin;
use App\Enums\Acces\CodeRole;
use App\Enums\Editions\StatutPhase;
use App\Enums\Scrutins\StatutCandidate;
use App\Models\Agent;
use App\Models\Bulletin;
use App\Models\Candidate;
use App\Models\Edition;
use App\Models\Phase;
use App\Models\Scrutin;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EcransDuVoteTest extends TestCase
{
    use RefreshDatabase;

    private Edition $edition;

    private Scrutin $scrutin;

    private User $electeur;

    private Agent $clarisse;

    private Agent $ines;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-11-18 10:15:00'));

        $this->edition = Edition::factory()->avecPhases()->enCours()->create([
            'annee' => 2026, 'date_debut' => '2026-11-01', 'date_fin' => '2026-12-31', 'date_gel' => '2026-10-01',
        ]);
        $dia = Structure::where('code', 'DIA')->firstOrFail();
        $depuis = Carbon::parse('2020-01-01');
        $this->clarisse = Agent::factory()->femme()->affecteA($dia, $depuis)->create(['nom' => 'AHOUANSOU', 'prenoms' => 'Clarisse']);
        $this->ines = Agent::factory()->femme()->affecteA($dia, $depuis)->create(['nom' => 'KPADE', 'prenoms' => 'Inès']);
        $marc = Agent::factory()->homme()->affecteA($dia, $depuis)->create();
        $this->electeur = User::factory()->pourAgent($marc)->avecRoles(CodeRole::Electeur)->create();

        app(GenererScrutins::class)->handle($this->edition->phase(Phase::PRIMAIRES));
        $this->edition->phase(Phase::PRIMAIRES)->update(['statut' => StatutPhase::Ouverte]);
        $this->scrutin = Scrutin::where('code', 'STR-DIA')->firstOrFail();
    }

    public function test_avant_l_ouverture_le_bulletin_n_est_pas_accessible(): void
    {
        $this->actingAs($this->electeur)->get(route('vote'))->assertOk()->assertSee('pas encore ouvert');
    }

    /**
     * R40, R43 : bulletin, confirmation, vote définitif et accusé sans le choix.
     */
    public function test_r40_vote_en_deux_etapes_puis_affiche_l_accuse_sans_le_choix(): void
    {
        $this->ouvrir();
        $clarisse = $this->candidate($this->clarisse);

        $this->actingAs($this->electeur)->get(route('vote'))
            ->assertOk()->assertSee('AHOUANSOU Clarisse')->assertSee('KPADE Inès')->assertDontSee('Vote blanc');
        $this->get(route('vote', ['recherche' => 'kpade']))->assertSee('KPADE Inès')->assertDontSee('AHOUANSOU Clarisse');

        $this->post(route('vote.confirmer'), ['candidate' => $clarisse->id])
            ->assertOk()->assertSee('Confirmer mon vote')->assertSee('AHOUANSOU Clarisse');
        $this->assertSame(0, Bulletin::count(), 'La confirmation n’enregistre rien.');

        $this->post(route('vote.store'), ['candidate' => $clarisse->id])->assertRedirect(route('vote'));

        $this->assertSame($clarisse->id, Bulletin::sole()->candidate_id);
        $numero = $this->scrutin->inscriptions()->where('agent_id', $this->electeur->agent_id)->value('numero_accuse');
        $this->get(route('vote'))->assertOk()->assertSee('Votre vote est enregistré')->assertSee($numero)->assertDontSee('AHOUANSOU');

        $this->post(route('vote.store'), ['candidate' => $clarisse->id])->assertRedirect(route('vote'));
        $this->assertSame(1, Bulletin::count(), 'R40 : un seul vote.');
    }

    /**
     * R33, R41 : seules les candidates actives du scrutin et, si l'édition l'autorise, le vote blanc.
     */
    public function test_r41_refuse_un_choix_hors_bulletin_et_propose_le_blanc_s_il_est_autorise(): void
    {
        $this->ouvrir();

        $this->actingAs($this->electeur)->post(route('vote.confirmer'), ['candidate' => Candidate::factory()->create()->id])
            ->assertRedirect(route('vote'))->assertSessionHasErrors('candidate');
        $this->post(route('vote.store'), ['candidate' => 'blanc'])->assertSessionHasErrors('candidate');

        $this->edition->update(['vote_blanc_autorise' => true]);
        $this->get(route('vote'))->assertSee('Vote blanc');
        $this->post(route('vote.store'), ['candidate' => 'blanc'])->assertRedirect(route('vote'));
        $this->assertTrue(Bulletin::sole()->est_blanc);
    }

    public function test_un_compte_sans_agent_n_accede_pas_au_vote(): void
    {
        $comite = User::factory()->avecRoles(CodeRole::Comite)->create();

        $this->actingAs($comite)->get(route('vote'))->assertForbidden();
    }

    /**
     * R32 à R34 : la candidate retire puis réactive sa candidature depuis son espace, jusqu'à l'ouverture.
     */
    public function test_r32_la_candidate_retire_et_reactive_sa_candidature_depuis_son_espace(): void
    {
        $compte = User::factory()->pourAgent($this->clarisse)->avecRoles(CodeRole::Electeur)->create();
        $candidature = $this->candidate($this->clarisse);

        $this->actingAs($compte)->get(route('espace'))->assertOk()->assertSee('Ma candidature')->assertSee('Retirer ma candidature');

        $this->post(route('candidatures.desister', $candidature), ['motif' => 'Raisons personnelles'])->assertSessionHas('succes');
        $this->assertSame(StatutCandidate::Desactivee, $candidature->fresh()->statut);
        $this->assertTrue($candidature->fresh()->desactiveePar->is($compte));
        $this->get(route('espace'))->assertSee('Réactiver ma candidature');

        $this->post(route('candidatures.reactiver', $candidature))->assertSessionHas('succes');
        $this->assertTrue($candidature->fresh()->estActive());

        $this->ouvrir();
        $this->post(route('candidatures.desister', $candidature))->assertSessionHasErrors('candidate');
        $this->assertTrue($candidature->fresh()->estActive());
    }

    public function test_un_electeur_ne_peut_pas_retirer_la_candidature_d_une_autre(): void
    {
        $this->actingAs($this->electeur)->post(route('candidatures.desister', $this->candidate($this->ines)))->assertForbidden();

        $this->assertTrue($this->candidate($this->ines)->estActive());
    }

    private function ouvrir(): void
    {
        app(ArreterListes::class)->handle($this->scrutin->fresh());
        app(PlanifierScrutin::class)->handle($this->scrutin->fresh(), now()->subMinutes(10), now()->addHours(6));
        app(OuvrirScrutin::class)->handle($this->scrutin);
    }

    private function candidate(Agent $agent): Candidate
    {
        return $this->scrutin->candidates()->where('agent_id', $agent->id)->firstOrFail();
    }
}
