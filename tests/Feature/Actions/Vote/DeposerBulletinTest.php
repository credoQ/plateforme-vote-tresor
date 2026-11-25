<?php

namespace Tests\Feature\Actions\Vote;

use App\Actions\Scrutins\ArreterListes;
use App\Actions\Scrutins\DesactiverCandidate;
use App\Actions\Scrutins\GenererScrutins;
use App\Actions\Scrutins\OuvrirScrutin;
use App\Actions\Vote\AccuseDeVote;
use App\Actions\Vote\DeposerBulletin;
use App\Actions\Vote\EmpreinteBulletin;
use App\Enums\Editions\StatutPhase;
use App\Enums\Scrutins\StatutScrutin;
use App\Models\Agent;
use App\Models\Bulletin;
use App\Models\Candidate;
use App\Models\Edition;
use App\Models\Inscription;
use App\Models\Phase;
use App\Models\ProcesVerbal;
use App\Models\Scrutin;
use App\Models\Structure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DeposerBulletinTest extends TestCase
{
    use RefreshDatabase;

    private Edition $edition;

    private Scrutin $scrutin;

    private Agent $clarisse;

    private Agent $ines;

    private Agent $marc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-18 10:37:12'));

        $this->edition = Edition::factory()->avecPhases()->enCours()->create([
            'annee' => 2026, 'date_debut' => '2026-11-01', 'date_fin' => '2026-12-31', 'date_gel' => '2026-10-01',
        ]);
        $dia = Structure::where('code', 'DIA')->firstOrFail();
        $depuis = Carbon::parse('2020-01-01');
        $this->clarisse = Agent::factory()->femme()->affecteA($dia, $depuis)->create();
        $this->ines = Agent::factory()->femme()->affecteA($dia, $depuis)->create();
        $this->marc = Agent::factory()->homme()->affecteA($dia, $depuis)->create();

        app(GenererScrutins::class)->handle($this->edition->phase(Phase::PRIMAIRES));
        $this->edition->phase(Phase::PRIMAIRES)->update(['statut' => StatutPhase::Ouverte]);
        $this->scrutin = Scrutin::where('code', 'STR-DIA')->firstOrFail();
        app(ArreterListes::class)->handle($this->scrutin->phase);
        $this->scrutin->update(['date_heure_ouverture' => now()->subHour(), 'date_heure_fermeture' => now()->addHours(8)]);
        app(OuvrirScrutin::class)->handle($this->scrutin);
        $this->scrutin->refresh();
    }

    /**
     * R40, R43 : émargement et bulletin enregistrés séparément, sans lien, horodatages tronqués à l'heure.
     */
    public function test_r43_enregistre_l_emargement_et_un_bulletin_sans_lien_avec_l_electeur(): void
    {
        $inscription = $this->scrutin->inscriptions()->where('agent_id', $this->marc->id)->firstOrFail();
        $miseAJour = $inscription->updated_at;

        $accuse = $this->voter($this->marc, $this->candidate($this->clarisse));

        $inscription->refresh();
        $this->assertTrue($inscription->a_vote);
        $this->assertSame('2026-11-18 10:00:00', $inscription->date_heure_vote->format('Y-m-d H:i:s'));
        $this->assertSame($accuse->numero, $inscription->numero_accuse);
        $this->assertTrue($inscription->updated_at->equalTo($miseAJour), 'La mise à jour de l’inscription ne doit pas dater le vote.');

        $bulletin = Bulletin::sole();
        $this->assertSame($this->candidate($this->clarisse)->id, $bulletin->candidate_id);
        $this->assertSame('2026-11-18 10:00:00', $bulletin->date_heure->format('Y-m-d H:i:s'));
        $this->assertSame([], array_intersect(Schema::getColumnListing('bulletins'), ['agent_id', 'user_id', 'inscription_id', 'created_at', 'updated_at']));
    }

    /**
     * R40 : un seul vote, définitif, et uniquement dans son scrutin.
     */
    public function test_r40_refuse_un_second_vote_et_le_vote_d_un_agent_non_inscrit(): void
    {
        $this->voter($this->marc, $this->candidate($this->clarisse));

        $this->attendreErreur(fn () => $this->voter($this->marc, $this->candidate($this->ines)), 'déjà voté');

        $horsListe = Agent::factory()->affecteA(Structure::where('code', 'DMG')->firstOrFail())->create();
        $this->attendreErreur(fn () => $this->voter($horsListe, $this->candidate($this->ines)), 'pas inscrit');
        $this->assertSame(1, Bulletin::count());
    }

    /**
     * R39 : pas de vote en dehors de la période d'ouverture.
     */
    public function test_r39_refuse_le_vote_apres_la_fermeture(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 19:37:12'));

        $this->attendreErreur(fn () => $this->voter($this->marc, $this->candidate($this->clarisse)), 'pas ouvert');
    }

    /**
     * R41 : le vote blanc n'est accepté que si l'édition l'autorise.
     */
    public function test_r41_le_vote_blanc_depend_du_parametre_de_l_edition(): void
    {
        $this->attendreErreur(fn () => $this->voter($this->marc, null), 'vote blanc');

        $this->edition->update(['vote_blanc_autorise' => true]);
        $this->voter($this->marc, null);

        $this->assertTrue(Bulletin::sole()->est_blanc);
        $this->assertNull(Bulletin::sole()->candidate_id);
    }

    /**
     * R33 : une candidate désactivée ou d'un autre scrutin ne peut recevoir aucune voix.
     */
    public function test_r33_refuse_une_voix_pour_une_candidate_hors_liste(): void
    {
        $autreScrutin = Candidate::factory()->create();

        $this->attendreErreur(fn () => $this->voter($this->marc, $autreScrutin), 'ne figure pas');
    }

    /**
     * R45 : le vote pour soi-même dépend du paramètre de l'édition.
     */
    public function test_r45_le_vote_pour_soi_meme_depend_du_parametre_de_l_edition(): void
    {
        $this->edition->update(['vote_pour_soi_autorise' => false]);

        $this->attendreErreur(fn () => $this->voter($this->clarisse, $this->candidate($this->clarisse)), 'soi-même');
        $this->voter($this->clarisse, $this->candidate($this->ines));
    }

    /**
     * R44 : les bulletins forment une chaîne d'empreintes intacte, non modifiable, et toute insertion frauduleuse est détectée.
     */
    public function test_r44_les_bulletins_forment_une_chaine_d_empreintes_inviolable(): void
    {
        $this->voter($this->marc, $this->candidate($this->clarisse));
        $this->voter($this->clarisse, $this->candidate($this->ines));
        $this->voter($this->ines, $this->candidate($this->clarisse));

        $empreinte = app(EmpreinteBulletin::class);
        $this->assertTrue($empreinte->chaineEstIntacte($this->scrutin->id));

        try {
            $ines = $this->candidate($this->ines)->id;
            DB::transaction(fn () => DB::table('bulletins')->update(['candidate_id' => $ines]));
            $this->fail('La modification d’un bulletin aurait dû être refusée.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('R44', $exception->getMessage());
        }

        DB::table('bulletins')->insert([
            'id' => (string) str()->uuid(), 'scrutin_id' => $this->scrutin->id, 'candidate_id' => $this->candidate($this->ines)->id,
            'est_blanc' => false, 'date_heure' => now()->startOfHour(), 'empreinte' => str_repeat('a', 64), 'empreinte_precedente' => str_repeat('b', 64),
        ]);
        $this->assertFalse($empreinte->chaineEstIntacte($this->scrutin->id));
    }

    /**
     * R44 : le scrutin garde la fin de sa chaîne, sans toucher son updated_at (R43) ; une fin de chaîne périmée
     * fait échouer le vote au lieu de créer un embranchement, et une fin qui ne correspond plus est détectée.
     */
    public function test_r44_le_scrutin_garde_la_fin_de_sa_chaine(): void
    {
        $modifieLe = $this->scrutin->fresh()->updated_at;
        $this->voter($this->marc, $this->candidate($this->clarisse));
        $premiere = Bulletin::sole()->empreinte;
        $this->voter($this->clarisse, $this->candidate($this->ines));

        $fin = Bulletin::where('empreinte_precedente', $premiere)->sole()->empreinte;
        $this->assertSame($fin, $this->scrutin->fresh()->derniere_empreinte);
        $this->assertEquals($modifieLe, $this->scrutin->fresh()->updated_at);

        DB::table('scrutins')->where('id', $this->scrutin->id)->update(['derniere_empreinte' => $premiere]);
        $this->assertFalse(app(EmpreinteBulletin::class)->chaineEstIntacte($this->scrutin->id));

        try {
            $this->voter($this->ines, $this->candidate($this->clarisse));
            $this->fail('Un bulletin chaîné sur une fin périmée aurait dû être refusé.');
        } catch (QueryException) {
            $this->assertSame(2, Bulletin::count());
            $this->assertFalse(Inscription::where('agent_id', $this->ines->id)->sole()->a_vote);
        }
    }

    /**
     * R46 : pendant le vote, seule la participation est exposée.
     */
    public function test_r46_calcule_le_taux_de_participation(): void
    {
        $this->voter($this->marc, $this->candidate($this->clarisse));

        $this->assertSame(['inscrits' => 3, 'votants' => 1, 'taux' => 33.33], $this->scrutin->participation());
    }

    /**
     * R38 : un scrutin dont toutes les candidates sont désactivées est annulé à son ouverture.
     */
    public function test_r38_annule_a_l_ouverture_un_scrutin_sans_candidate_active(): void
    {
        $scrutin = Scrutin::factory()->for($this->edition->phase(Phase::PRIMAIRES))->statut(StatutScrutin::ListesArretees)
            ->create(['date_heure_ouverture' => now()->subMinute(), 'date_heure_fermeture' => now()->addHour()]);
        $candidate = Candidate::factory()->for($scrutin)->create();
        app(DesactiverCandidate::class)->handle($candidate);

        $this->assertSame(StatutScrutin::Annule, app(OuvrirScrutin::class)->handle($scrutin)->statut);
    }

    /**
     * R47 : à la fermeture, la commande de bascule clôt le scrutin puis le dépouille aussitôt.
     */
    public function test_r47_la_commande_de_bascule_ferme_puis_depouille_les_scrutins_arrives_a_echeance(): void
    {
        $this->voter($this->marc, $this->candidate($this->clarisse));
        $this->travelTo(Carbon::parse('2026-11-18 18:40:00'));

        $this->artisan('scrutins:basculer')->assertSuccessful();

        $scrutin = $this->scrutin->fresh();
        $this->assertSame(StatutScrutin::Clos, $scrutin->statut);
        $this->assertSame(1, $scrutin->procesVerbal->nb_votants);
        $this->assertSame(1, $this->candidate($this->clarisse)->fresh()->nb_voix);

        $this->artisan('scrutins:basculer')->assertSuccessful();
        $this->assertSame(1, ProcesVerbal::where('scrutin_id', $scrutin->id)->count(), 'Un scrutin dépouillé ne l’est pas de nouveau.');
    }

    public function test_la_commande_de_bascule_ouvre_les_scrutins_dont_l_heure_est_venue(): void
    {
        $scrutin = Scrutin::factory()->for($this->edition->phase(Phase::PRIMAIRES))->statut(StatutScrutin::ListesArretees)
            ->create(['date_heure_ouverture' => now()->addMinutes(5), 'date_heure_fermeture' => now()->addHours(2)]);
        Candidate::factory()->for($scrutin)->create();

        $this->artisan('scrutins:basculer')->assertSuccessful();
        $this->assertSame(StatutScrutin::ListesArretees, $scrutin->fresh()->statut);

        $this->travel(6)->minutes();
        $this->artisan('scrutins:basculer')->assertSuccessful();
        $this->assertSame(StatutScrutin::Ouvert, $scrutin->fresh()->statut);
    }

    private function voter(Agent $electeur, ?Candidate $choix): AccuseDeVote
    {
        return app(DeposerBulletin::class)->handle($electeur, $this->scrutin, $choix);
    }

    private function candidate(Agent $agent): Candidate
    {
        return $this->scrutin->candidates()->where('agent_id', $agent->id)->firstOrFail();
    }

    private function attendreErreur(callable $action, string $extrait): void
    {
        try {
            $action();
            $this->fail('Une erreur de validation était attendue.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($extrait, implode(' ', array_merge(...array_values($exception->errors()))));
        }
    }
}
