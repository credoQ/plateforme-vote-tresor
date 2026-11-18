<?php

namespace Tests\Feature\Http\Comite;

use App\Actions\Scrutins\ArreterListes;
use App\Actions\Scrutins\GenererScrutins;
use App\Actions\Scrutins\OuvrirScrutin;
use App\Actions\Scrutins\PlanifierScrutin;
use App\Actions\Vote\DeposerBulletin;
use App\Enums\Acces\CodeRole;
use App\Enums\Editions\StatutPhase;
use App\Enums\Scrutins\StatutScrutin;
use App\Jobs\Resultats\GenererFichierProcesVerbal;
use App\Models\Agent;
use App\Models\Candidate;
use App\Models\Edition;
use App\Models\Phase;
use App\Models\Scrutin;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResultatsTest extends TestCase
{
    use RefreshDatabase;

    private User $comite;

    private Scrutin $scrutin;

    /**
     * @var list<Agent>
     */
    private array $femmes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Notification::fake();
        Storage::fake('local');
        $this->travelTo(Carbon::parse('2026-11-18 08:10:00'));

        $this->comite = User::factory()->avecRoles(CodeRole::Comite)->create();
        $edition = Edition::factory()->avecPhases()->enCours()->create([
            'annee' => 2026, 'date_debut' => '2026-11-01', 'date_fin' => '2026-12-31', 'date_gel' => '2026-10-01',
        ]);
        $dia = Structure::where('code', 'DIA')->firstOrFail();
        $this->femmes = Agent::factory()->femme()->affecteA($dia, Carbon::parse('2020-01-01'))->count(3)->create()->all();

        app(GenererScrutins::class)->handle($edition->phase(Phase::PRIMAIRES));
        $edition->phase(Phase::PRIMAIRES)->update(['statut' => StatutPhase::Ouverte]);
        $this->scrutin = Scrutin::where('code', 'STR-DIA')->firstOrFail();
        app(ArreterListes::class)->handle($this->scrutin);
        app(PlanifierScrutin::class)->handle($this->scrutin->fresh(), now()->subMinutes(5), now()->addHours(2));
        app(OuvrirScrutin::class)->handle($this->scrutin);
    }

    /**
     * R46 à R51, R54 : fermeture, dépouillement, validation, procès-verbal et publication depuis l'écran du scrutin.
     */
    public function test_r51_conduit_le_scrutin_du_depouillement_a_la_publication(): void
    {
        $this->voter([0, 0, 1]);
        $this->actingAs($this->comite)->get(route('scrutins.show', $this->scrutin))->assertDontSee('Résultats provisoires');

        $this->travelTo(Carbon::parse('2026-11-18 11:00:00'));
        $this->post(route('scrutins.fermer', $this->scrutin))->assertSessionHasNoErrors();
        $this->get(route('scrutins.show', $this->scrutin))->assertSee('Dépouiller le scrutin');

        $this->post(route('resultats.depouiller', $this->scrutin))->assertSessionHasNoErrors();
        $this->get(route('scrutins.show', $this->scrutin))->assertSee('Résultats provisoires')->assertSee('Lauréate')->assertSee('Valider les résultats');

        $this->post(route('resultats.valider', $this->scrutin))->assertSessionHas('succes');
        $this->get(route('scrutins.show', $this->scrutin))->assertSee('PV n° PV-2026-P1-001')->assertSee('Publier les résultats');
        $this->get(route('resultats.proces-verbal', $this->scrutin))->assertOk()->assertSee('PV-2026-P1-001');
        $pdf = $this->get(route('resultats.proces-verbal', [$this->scrutin, 'format' => 'pdf']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->post(route('resultats.publier', $this->scrutin))->assertSessionHasNoErrors();
        $this->assertSame(StatutScrutin::ResultatsPublies, $this->scrutin->fresh()->statut);

        $electrice = User::factory()->pourAgent($this->femmes[2])->avecRoles(CodeRole::Electeur)->create();
        $this->actingAs($electrice)->get(route('palmares'))->assertOk()->assertSee($this->femmes[0]->nomComplet());
        $this->get(route('resultats.proces-verbal', $this->scrutin))->assertOk();
    }

    /**
     * R49 : l'égalité est départagée par le comité depuis l'écran, avant la validation.
     */
    public function test_r49_departage_une_egalite_depuis_l_ecran(): void
    {
        $this->voter([0, 1]);
        $this->travelTo(Carbon::parse('2026-11-18 11:00:00'));
        $this->actingAs($this->comite)->post(route('scrutins.fermer', $this->scrutin));
        $this->post(route('resultats.depouiller', $this->scrutin));

        $this->get(route('scrutins.show', $this->scrutin))->assertSee('Égalité à départager')->assertDontSee('Valider les résultats');
        $this->post(route('resultats.valider', $this->scrutin))->assertSessionHasErrors('scrutin');

        $retenue = $this->candidate(1);
        $this->post(route('resultats.trancher', $this->scrutin), ['retenues' => [$retenue->id], 'motif' => 'Second tour'])->assertSessionHasNoErrors();
        $this->assertTrue($retenue->fresh()->est_laureate);
        $this->get(route('scrutins.show', $this->scrutin))->assertSee('Départage par le comité : Second tour')->assertSee('Valider les résultats');
    }

    /**
     * R46 : avant la validation, ni le point focal ni l'électeur ne voient les résultats ; le procès-verbal n'est pas public.
     */
    public function test_r46_les_resultats_provisoires_restent_reserves_au_comite(): void
    {
        $this->voter([0]);
        $this->travelTo(Carbon::parse('2026-11-18 11:00:00'));
        $this->actingAs($this->comite)->post(route('scrutins.fermer', $this->scrutin));
        $this->post(route('resultats.depouiller', $this->scrutin));
        $this->post(route('resultats.valider', $this->scrutin));

        $pointFocal = User::factory()->pointFocalDe(Structure::where('code', 'DIA')->firstOrFail())->create();
        $this->actingAs($pointFocal)->get(route('scrutins.show', $this->scrutin))->assertOk()->assertDontSee('PV n°')->assertDontSee('id="resultats"', false);
        $this->get(route('resultats.proces-verbal', $this->scrutin))->assertForbidden();
        $this->post(route('resultats.publier', $this->scrutin))->assertForbidden();
    }

    public function test_relance_la_generation_d_un_proces_verbal_absent(): void
    {
        Queue::fake();
        $this->voter([0]);
        $this->travelTo(Carbon::parse('2026-11-18 11:00:00'));
        $this->actingAs($this->comite)->post(route('scrutins.fermer', $this->scrutin));
        $this->post(route('resultats.depouiller', $this->scrutin));
        $this->post(route('resultats.valider', $this->scrutin));

        $this->get(route('scrutins.show', $this->scrutin))->assertSee('Relancer la génération du procès-verbal');
        $this->post(route('resultats.regenerer', $this->scrutin))->assertSessionHas('succes');

        Queue::assertPushed(GenererFichierProcesVerbal::class);
    }

    /**
     * @param  list<int>  $choix  index de la candidate choisie par chaque votante
     */
    private function voter(array $choix): void
    {
        foreach ($choix as $position => $indice) {
            app(DeposerBulletin::class)->handle($this->femmes[$position], $this->scrutin, $this->candidate($indice));
        }
    }

    private function candidate(int $indice): Candidate
    {
        return $this->scrutin->candidates()->where('agent_id', $this->femmes[$indice]->id)->firstOrFail();
    }
}
