<?php

namespace Tests\Feature\Suivi;

use App\Actions\Scrutins\ArreterListes;
use App\Actions\Scrutins\GenererScrutins;
use App\Actions\Scrutins\OuvrirScrutin;
use App\Actions\Scrutins\PlanifierScrutin;
use App\Actions\Vote\DeposerBulletin;
use App\Enums\Acces\CodeRole;
use App\Enums\Editions\StatutPhase;
use App\Enums\Referentiel\CodeTypeStructure;
use App\Enums\Scrutins\StatutScrutin;
use App\Models\Agent;
use App\Models\Edition;
use App\Models\JournalAudit;
use App\Models\Phase;
use App\Models\Scrutin;
use App\Models\Structure;
use App\Models\User;
use App\Notifications\AnnonceScrutin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SuiviEtAnnoncesTest extends TestCase
{
    use RefreshDatabase;

    private Edition $edition;

    private Scrutin $scrutinAtl;

    private Agent $electriceAtl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-11-18 09:30:00'));

        $this->edition = Edition::factory()->avecPhases()->enCours()->create([
            'annee' => 2026, 'date_debut' => '2026-11-01', 'date_fin' => '2026-12-31', 'date_gel' => '2026-10-01',
        ]);
        $depuis = Carbon::parse('2020-01-01');
        $tdAtl = Structure::where('code', 'TD-ATL')->firstOrFail();
        $tc = Structure::factory()->deType(CodeTypeStructure::TresorerieCommunale)
            ->create(['code' => 'TC-OUIDAH', 'departement_id' => $tdAtl->departement_id, 'structure_parente_id' => $tdAtl->id]);
        $this->electriceAtl = Agent::factory()->femme()->affecteA($tdAtl, $depuis)->create(['email' => 'electrice@example.test']);
        Agent::factory()->homme()->affecteA($tc, $depuis)->create();
        Agent::factory()->femme()->affecteA(Structure::where('code', 'DIA')->firstOrFail(), $depuis)->create();

        app(GenererScrutins::class)->handle($this->edition->phase(Phase::PRIMAIRES));
        $this->edition->phase(Phase::PRIMAIRES)->update(['statut' => StatutPhase::Ouverte]);
        $this->scrutinAtl = Scrutin::where('code', 'DEP-ATL')->firstOrFail();
        app(ArreterListes::class)->handle($this->scrutinAtl);
        app(PlanifierScrutin::class)->handle($this->scrutinAtl->fresh(), now()->subMinute(), now()->addHours(6));
        app(OuvrirScrutin::class)->handle($this->scrutinAtl);
    }

    /**
     * Module 08 : participation par scrutin, département et structure, sans aucune voix (R46).
     */
    public function test_r46_le_tableau_de_bord_suit_la_participation_sans_les_voix(): void
    {
        $candidate = $this->scrutinAtl->candidates()->sole();
        app(DeposerBulletin::class)->handle($this->electriceAtl, $this->scrutinAtl, $candidate);
        $comite = User::factory()->avecRoles(CodeRole::Comite)->create();

        $this->actingAs($comite)->get(route('tableau-de-bord'))
            ->assertOk()
            ->assertSee('Scrutin départemental · Atlantique')
            ->assertSee('50,0 %')
            ->assertSee('Atlantique')
            ->assertSee('TC-OUIDAH')
            ->assertSee('33,3 %')
            ->assertDontSee('Voix');
    }

    /**
     * R4 : le point focal ne suit que sa structure.
     */
    public function test_r4_le_point_focal_ne_suit_que_sa_structure(): void
    {
        $pointFocal = User::factory()->pointFocalDe(Structure::where('code', 'DIA')->firstOrFail())->create();
        $electeur = User::factory()->pourAgent(Agent::factory()->create())->avecRoles(CodeRole::Electeur)->create();

        $this->actingAs($electeur)->get(route('tableau-de-bord'))->assertForbidden();
        $this->actingAs($pointFocal)->get(route('tableau-de-bord'))
            ->assertOk()
            ->assertSee('Données limitées à votre structure')
            ->assertSee('Scrutin de structure · Direction de l&#039;Informatique et des Archives', false)
            ->assertDontSee('TC-OUIDAH');
    }

    public function test_exporte_la_liste_electorale_et_les_resultats_publies_seulement(): void
    {
        $comite = User::factory()->avecRoles(CodeRole::Comite)->create();

        $liste = $this->actingAs($comite)->get(route('exports.liste-electorale', $this->scrutinAtl));
        $liste->assertOk();
        $this->assertStringContainsString($this->electriceAtl->matricule, $liste->streamedContent());
        $this->assertStringContainsString('TC-OUIDAH', $liste->streamedContent());

        $this->get(route('exports.resultats', $this->scrutinAtl))->assertNotFound();

        $this->scrutinAtl->update(['statut' => StatutScrutin::ResultatsPublies]);
        $this->assertStringContainsString($this->electriceAtl->matricule, $this->get(route('exports.resultats', $this->scrutinAtl))->streamedContent());
    }

    /**
     * Module 08 : à l'ouverture, une annonce part vers les inscrits dont l'e-mail est connu ; le vote n'est jamais journalisé (R57).
     */
    public function test_annonce_l_ouverture_aux_inscrits_ayant_un_email_et_ne_journalise_pas_le_vote(): void
    {
        Notification::assertSentTimes(AnnonceScrutin::class, 1);
        Notification::assertSentTo(new AnonymousNotifiable, AnnonceScrutin::class, fn (AnnonceScrutin $n, array $canaux, AnonymousNotifiable $dest) => $dest->routes['mail'] === 'electrice@example.test' && $n->type === AnnonceScrutin::OUVERTURE);

        $avant = JournalAudit::count();
        app(DeposerBulletin::class)->handle($this->electriceAtl, $this->scrutinAtl, $this->scrutinAtl->candidates()->sole());
        $this->assertSame($avant, JournalAudit::count());
    }

    public function test_l_accueil_affiche_les_annonces_des_scrutins_ouverts(): void
    {
        $electrice = User::factory()->pourAgent($this->electriceAtl)->avecRoles(CodeRole::Electeur)->create();

        $this->actingAs($electrice)->get(route('espace'))->assertOk()->assertSee('Vote ouvert')->assertSee('Scrutin départemental · Atlantique');
    }
}
