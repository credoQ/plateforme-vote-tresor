<?php

namespace Tests\Feature\Suivi;

use App\Actions\Audit\Journaliser;
use App\Actions\Editions\LancerEdition;
use App\Actions\Scrutins\DesactiverCandidate;
use App\Enums\Acces\CodeRole;
use App\Enums\Audit\ActionAuditee;
use App\Models\Agent;
use App\Models\Candidate;
use App\Models\Edition;
use App\Models\JournalAudit;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class JournalAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * R56 : le journal est en ajout seul, même par une requête SQL directe.
     */
    public function test_r56_le_journal_est_en_ajout_seul(): void
    {
        $entree = app(Journaliser::class)->handle(ActionAuditee::ImportAgents, null, ['crees' => 3]);

        foreach (["UPDATE journal_audit SET login = 'pirate' WHERE id = {$entree->id}", "DELETE FROM journal_audit WHERE id = {$entree->id}", 'TRUNCATE journal_audit'] as $requete) {
            try {
                DB::transaction(fn () => DB::statement($requete));
                $this->fail("La requête aurait dû être refusée : {$requete}");
            } catch (QueryException $exception) {
                $this->assertStringContainsString('R56', $exception->getMessage());
            }
        }

        $this->assertSame(['crees' => 3], $entree->fresh()->details);
    }

    /**
     * R56 : connexions réussies et échouées, déconnexions ; jamais de mot de passe.
     */
    public function test_r56_journalise_les_connexions_sans_mot_de_passe(): void
    {
        $utilisateur = User::factory()->create(['login' => '102345', 'password' => 'secret-2026']);

        $this->post(route('connexion.store'), ['login' => '102345', 'password' => 'mauvais-mot']);
        $this->post(route('connexion.store'), ['login' => '102345', 'password' => 'secret-2026']);
        $this->post(route('deconnexion'));

        $this->assertSame(['CONNEXION_ECHOUEE', 'CONNEXION', 'DECONNEXION'], JournalAudit::orderBy('id')->pluck('action')->map->value->all());
        $connexion = JournalAudit::where('action', ActionAuditee::Connexion->value)->sole();
        $this->assertSame($utilisateur->id, $connexion->user_id);
        $this->assertSame('127.0.0.1', $connexion->adresse_ip);
        $this->assertSame('102345', JournalAudit::where('action', ActionAuditee::ConnexionEchouee->value)->sole()->login);
        $this->assertDatabaseMissing('journal_audit', ['details' => json_encode(['password' => 'mauvais-mot'])]);
        $this->assertStringNotContainsString('mauvais-mot', JournalAudit::all()->toJson());
    }

    /**
     * R56 : les opérations sensibles du comité sont tracées avec leur auteur et leur motif.
     */
    public function test_r56_journalise_les_operations_sensibles_avec_leur_auteur(): void
    {
        $comite = User::factory()->avecRoles(CodeRole::Comite)->create();
        $this->actingAs($comite);
        $candidate = Candidate::factory()->create();

        app(DesactiverCandidate::class)->handle($candidate, $comite, 'Demande écrite');

        $entree = JournalAudit::where('action', ActionAuditee::CandidateDesactivee->value)->sole();
        $this->assertSame($comite->id, $entree->user_id);
        $this->assertSame(['Candidate', $candidate->id], [$entree->entite_type, $entree->entite_id]);
        $this->assertSame('Demande écrite', $entree->details['motif']);
    }

    public function test_une_operation_refusee_n_est_pas_journalisee(): void
    {
        Edition::factory()->avecPhases()->create();

        try {
            app(LancerEdition::class)->handle(Edition::firstOrFail());
        } catch (ValidationException) {
        }

        $this->assertSame(0, JournalAudit::where('action', ActionAuditee::EditionLancee->value)->count());
    }

    public function test_le_journal_est_consultable_par_l_observateur_seulement(): void
    {
        app(Journaliser::class)->handle(ActionAuditee::ImportAgents, null, ['crees' => 3]);
        $observateur = User::factory()->avecRoles(CodeRole::Observateur)->create();
        $electeur = User::factory()->pourAgent(Agent::factory()->create())->avecRoles(CodeRole::Electeur)->create();

        $this->actingAs($electeur)->get(route('journal'))->assertForbidden();
        $this->actingAs($observateur)->get(route('journal'))->assertOk()->assertSee('{&quot;crees&quot;:3}', false);
        $this->get(route('journal', ['action' => 'CONNEXION']))->assertOk()->assertDontSee('{&quot;crees&quot;:3}', false);
    }
}
