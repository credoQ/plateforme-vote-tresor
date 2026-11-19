<?php

namespace Tests\Feature\Actions\Scrutins;

use App\Actions\Scrutins\ArreterListes;
use App\Actions\Scrutins\ConstituerListes;
use App\Actions\Scrutins\DesactiverCandidate;
use App\Actions\Scrutins\GenererScrutins;
use App\Actions\Scrutins\ReactiverCandidate;
use App\Actions\Scrutins\RouvrirListes;
use App\Enums\Audit\ActionAuditee;
use App\Enums\Scrutins\StatutCandidate;
use App\Enums\Scrutins\StatutScrutin;
use App\Models\Agent;
use App\Models\Candidate;
use App\Models\Edition;
use App\Models\JournalAudit;
use App\Models\Phase;
use App\Models\Scrutin;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ListesEtCandidatesTest extends TestCase
{
    use RefreshDatabase;

    private Scrutin $scrutin;

    private Candidate $candidate;

    protected function setUp(): void
    {
        parent::setUp();

        $edition = Edition::factory()->avecPhases()->enCours()->create([
            'annee' => 2026, 'date_debut' => '2026-11-01', 'date_fin' => '2026-12-31', 'date_gel' => '2026-10-01',
        ]);
        $dia = Structure::where('code', 'DIA')->firstOrFail();
        $femme = Agent::factory()->femme()->affecteA($dia, Carbon::parse('2020-01-01'))->create();
        Agent::factory()->femme()->affecteA($dia, Carbon::parse('2020-01-01'))->create();

        app(GenererScrutins::class)->handle($edition->phase(Phase::PRIMAIRES));
        $this->scrutin = Scrutin::where('code', 'STR-DIA')->firstOrFail();
        $this->candidate = $this->scrutin->candidates()->where('agent_id', $femme->id)->firstOrFail();
    }

    /**
     * R32, R33 : la désactivation est tracée ; la candidate sort de la liste mais reste électrice.
     */
    public function test_r33_desactive_une_candidate_en_tracant_la_date_l_auteur_et_le_motif(): void
    {
        $pointFocal = User::factory()->create();

        app(DesactiverCandidate::class)->handle($this->candidate, $pointFocal, 'Ne souhaite pas concourir');

        $candidate = $this->candidate->fresh();
        $this->assertSame(StatutCandidate::Desactivee, $candidate->statut);
        $this->assertNotNull($candidate->date_desistement);
        $this->assertSame('Ne souhaite pas concourir', $candidate->motif_desistement);
        $this->assertTrue($candidate->desactiveePar->is($pointFocal));
        $this->assertSame(1, $this->scrutin->candidates()->actives()->count());
        $this->assertTrue($this->scrutin->inscriptions()->where('agent_id', $candidate->agent_id)->exists());
    }

    /**
     * R33 : une actualisation des listes ne réactive pas une candidate désactivée.
     */
    public function test_r33_une_candidate_desactivee_le_reste_apres_actualisation_des_listes(): void
    {
        app(DesactiverCandidate::class)->handle($this->candidate);

        app(ConstituerListes::class)->handle($this->scrutin);

        $this->assertSame(StatutCandidate::Desactivee, $this->candidate->fresh()->statut);
    }

    /**
     * R34 : réactivation possible tant que le scrutin n'est pas ouvert.
     */
    public function test_r34_reactive_une_candidate_apres_l_arret_des_listes_mais_pas_apres_l_ouverture(): void
    {
        app(DesactiverCandidate::class)->handle($this->candidate, null, 'Erreur');
        app(ArreterListes::class)->handle($this->scrutin);

        app(ReactiverCandidate::class)->handle($this->candidate->fresh());
        $this->assertTrue($this->candidate->fresh()->estActive());
        $this->assertNull($this->candidate->fresh()->motif_desistement);

        $this->scrutin->update(['statut' => StatutScrutin::Ouvert]);

        $this->expectException(ValidationException::class);
        app(DesactiverCandidate::class)->handle($this->candidate->fresh());
    }

    /**
     * R27, R30 : une fois arrêtées, les listes ne sont plus actualisées.
     */
    public function test_r27_les_listes_arretees_ne_sont_plus_modifiables(): void
    {
        $scrutin = app(ArreterListes::class)->handle($this->scrutin);
        $this->assertSame(StatutScrutin::ListesArretees, $scrutin->fresh()->statut);

        Agent::factory()->femme()->affecteA(Structure::where('code', 'DIA')->firstOrFail(), Carbon::parse('2020-01-01'))->create();

        $this->expectException(ValidationException::class);
        app(ConstituerListes::class)->handle($scrutin->fresh());
    }

    /**
     * R27, R28, R33 : après une réouverture motivée et tracée, les listes recalculées intègrent l'agente omise ;
     * une candidate désactivée le reste.
     */
    public function test_r27_rouvre_les_listes_pour_les_corriger_puis_les_arrete_de_nouveau(): void
    {
        app(DesactiverCandidate::class)->handle($this->candidate, User::factory()->create(), null);
        app(ArreterListes::class)->handle($this->scrutin);
        $omise = Agent::factory()->femme()->affecteA(Structure::where('code', 'DIA')->firstOrFail(), Carbon::parse('2020-01-01'))->create();

        app(RouvrirListes::class)->handle($this->scrutin->fresh(), 'Agente omise de l’import RH');

        $journal = JournalAudit::where('action', ActionAuditee::ListesRouvertes->value)->sole();
        $this->assertSame('Agente omise de l’import RH', $journal->details['motif']);

        $scrutin = app(ArreterListes::class)->handle($this->scrutin->fresh());
        $this->assertSame(StatutScrutin::ListesArretees, $scrutin->fresh()->statut);
        $this->assertTrue($scrutin->inscriptions()->where('agent_id', $omise->id)->exists());
        $this->assertTrue($scrutin->candidates()->where('agent_id', $omise->id)->exists());
        $this->assertSame(StatutCandidate::Desactivee, $this->candidate->fresh()->statut);
    }

    /**
     * R27 : une fois le scrutin ouvert, les listes ne peuvent plus être rouvertes.
     */
    public function test_r27_refuse_de_rouvrir_les_listes_d_un_scrutin_ouvert(): void
    {
        app(ArreterListes::class)->handle($this->scrutin);
        $this->scrutin->update(['statut' => StatutScrutin::Ouvert]);

        $this->expectException(ValidationException::class);
        app(RouvrirListes::class)->handle($this->scrutin->fresh(), 'Trop tard');
    }

    /**
     * R14 : les listes ne s'arrêtent qu'à partir de la date de gel.
     */
    public function test_r14_refuse_d_arreter_les_listes_avant_la_date_de_gel(): void
    {
        $this->travelTo(Carbon::parse('2026-09-30'));

        $this->expectException(ValidationException::class);
        app(ArreterListes::class)->handle($this->scrutin);
    }
}
