<?php

namespace Tests\Feature\Actions\Scrutins;

use App\Actions\Scrutins\ArreterListes;
use App\Actions\Scrutins\ConstituerListes;
use App\Actions\Scrutins\GenererScrutins;
use App\Actions\Scrutins\NominerCandidate;
use App\Actions\Scrutins\RetirerNomination;
use App\Enums\Audit\ActionAuditee;
use App\Enums\Referentiel\CodePoleElectoral;
use App\Enums\Scrutins\OrigineCandidate;
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

/**
 * R28, R58 : sur un collège à nomination, le comité saisit les nominées jusqu'à l'arrêt des listes.
 */
class NominationTest extends TestCase
{
    use RefreshDatabase;

    private Scrutin $scrutin;

    private User $comite;

    /**
     * @var array<string, Agent>
     */
    private array $agents;

    protected function setUp(): void
    {
        parent::setUp();

        $edition = Edition::factory()->avecPhases()->enCours()->create([
            'annee' => 2026, 'date_debut' => '2026-11-01', 'date_fin' => '2026-12-31', 'date_gel' => '2026-10-01',
        ]);
        $edition->phase(Phase::PRIMAIRES)->colleges()->with('poleElectoral')->get()
            ->firstWhere('poleElectoral.code', CodePoleElectoral::Structures)
            ->update(['origine_candidates' => OrigineCandidate::Nomination]);

        $depuis = Carbon::parse('2020-01-01');
        $this->agents = [
            'clarisse' => Agent::factory()->femme()->affecteA($this->structure('DIA'), $depuis)->create(['matricule' => '104876']),
            'ines' => Agent::factory()->femme()->affecteA($this->structure('DIA'), $depuis)->create(['matricule' => '104877']),
            'paul' => Agent::factory()->homme()->affecteA($this->structure('DIA'), $depuis)->create(['matricule' => '104878']),
            'atl' => Agent::factory()->femme()->affecteA($this->structure('TD-ATL'), $depuis)->create(['matricule' => '204001']),
        ];

        app(GenererScrutins::class)->handle($edition->phase(Phase::PRIMAIRES));
        $this->scrutin = Scrutin::where('code', 'STR-DIA')->firstOrFail();
        $this->comite = User::factory()->create();
    }

    public function test_r58_le_comite_nomine_une_electrice_avec_la_reference_de_la_decision(): void
    {
        $this->assertSame(0, $this->scrutin->candidates()->count(), 'Aucune candidate d’office sur un collège à nomination.');

        $candidate = $this->nominer('104876', 'PV du CODIR du 25/11/2026');

        $this->assertSame(OrigineCandidate::Nomination, $candidate->origine);
        $this->assertSame('PV du CODIR du 25/11/2026', $candidate->reference_nomination);
        $this->assertTrue($candidate->nommeePar->is($this->comite));
        $this->assertNotNull($candidate->date_nomination);
        $journal = JournalAudit::where('action', ActionAuditee::CandidateNominee->value)->sole();
        $this->assertSame(['scrutin' => 'STR-DIA', 'matricule' => '104876', 'reference' => 'PV du CODIR du 25/11/2026'], $journal->details);
    }

    /**
     * R58 : seule une électrice du scrutin, pas encore candidate, avec une référence, sur un collège à nomination.
     */
    public function test_r58_refuse_une_nomination_irreguliere(): void
    {
        $this->nominer('104876', 'PV 1');

        $this->attendreErreur('matricule', 'pas une agent de sexe féminin', fn () => $this->nominer('104878', 'PV 2'));
        $this->attendreErreur('matricule', 'n’est inscrite sur la liste', fn () => $this->nominer('204001', 'PV 2'));
        $this->attendreErreur('matricule', 'n’est inscrite sur la liste', fn () => $this->nominer('999999', 'PV 2'));
        $this->attendreErreur('matricule', 'déjà candidate', fn () => $this->nominer('104876', 'PV 2'));
        $this->attendreErreur('reference_nomination', 'référence', fn () => $this->nominer('104877', '  '));

        $departemental = Scrutin::where('code', 'DEP-ATL')->firstOrFail();
        $this->attendreErreur('matricule', 'ne sont pas nominées', fn () => app(NominerCandidate::class)->handle($departemental, '204001', 'PV 3', $this->comite));
    }

    /**
     * R27, R58 : après l'arrêt des listes, ni nomination ni retrait ; sans nominée, les listes ne s'arrêtent pas.
     */
    public function test_r58_les_nominations_se_figent_a_l_arret_des_listes(): void
    {
        $this->attendreErreur('listes', 'aucune candidate', fn () => app(ArreterListes::class)->handle($this->scrutin->phase));

        $candidate = $this->nominer('104876', 'PV 1');
        app(ArreterListes::class)->handle($this->scrutin->phase);

        $this->assertSame(StatutScrutin::ListesArretees, $this->scrutin->fresh()->statut);
        $this->assertSame(1, $this->scrutin->candidates()->count(), 'L’actualisation finale conserve les nominées.');
        $this->attendreErreur('matricule', 'arrêtées', fn () => $this->nominer('104877', 'PV 2'));
        $this->attendreErreur('candidate', 'arrêtées', fn () => app(RetirerNomination::class)->handle($candidate, $this->comite));
    }

    public function test_r58_retire_une_nomination_saisie_par_erreur(): void
    {
        $candidate = $this->nominer('104876', 'PV 1');

        app(RetirerNomination::class)->handle($candidate, $this->comite);

        $this->assertSame(0, $this->scrutin->candidates()->count());
        $this->assertSame(1, JournalAudit::where('action', ActionAuditee::NominationRetiree->value)->count());
    }

    /**
     * R15, R29, R58 : une nominée qui n'est plus inscrite à l'actualisation (affectation corrigée avant le gel) n'est plus candidate.
     */
    public function test_r58_l_actualisation_retire_une_nominee_qui_n_est_plus_inscrite(): void
    {
        $this->nominer('104876', 'PV 1');
        $this->nominer('104877', 'PV 2');
        $this->agents['ines']->affectations()->update(['date_fin' => '2026-09-30']);

        app(ConstituerListes::class)->handle($this->scrutin->fresh());

        $this->assertSame([$this->agents['clarisse']->id], $this->scrutin->candidates()->pluck('agent_id')->all());
    }

    private function nominer(string $matricule, string $reference): Candidate
    {
        return app(NominerCandidate::class)->handle($this->scrutin, $matricule, $reference, $this->comite);
    }

    private function structure(string $code): Structure
    {
        return Structure::where('code', $code)->firstOrFail();
    }

    private function attendreErreur(string $champ, string $extrait, callable $action): void
    {
        try {
            $action();
            $this->fail("Une erreur sur « {$champ} » était attendue.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($champ, $exception->errors());
            $this->assertStringContainsString($extrait, implode(' ', $exception->errors()[$champ]));
        }
    }
}
