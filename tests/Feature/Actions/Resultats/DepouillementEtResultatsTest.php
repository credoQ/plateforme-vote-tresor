<?php

namespace Tests\Feature\Actions\Resultats;

use App\Actions\Resultats\DepouillerScrutin;
use App\Actions\Resultats\EtablirPalmares;
use App\Actions\Resultats\ProlongerScrutin;
use App\Actions\Resultats\PublierResultats;
use App\Actions\Resultats\TrancherEgalite;
use App\Actions\Resultats\ValiderResultats;
use App\Actions\Scrutins\ArreterListes;
use App\Actions\Scrutins\DesactiverCandidate;
use App\Actions\Scrutins\FermerScrutin;
use App\Actions\Scrutins\GenererScrutins;
use App\Actions\Scrutins\OuvrirScrutin;
use App\Actions\Scrutins\PlanifierScrutin;
use App\Actions\Vote\DeposerBulletin;
use App\Enums\Acces\CodeRole;
use App\Enums\Editions\StatutPhase;
use App\Enums\Scrutins\StatutScrutin;
use App\Jobs\Resultats\EnvoyerQualifications;
use App\Jobs\Resultats\GenererFichierProcesVerbal;
use App\Models\Agent;
use App\Models\Candidate;
use App\Models\Edition;
use App\Models\Phase;
use App\Models\ProcesVerbal;
use App\Models\Scrutin;
use App\Models\Structure;
use App\Models\User;
use App\Notifications\ProclamationFemmeModele;
use App\Notifications\QualificationCandidate;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DepouillementEtResultatsTest extends TestCase
{
    use RefreshDatabase;

    private Edition $edition;

    private Scrutin $scrutin;

    /**
     * @var list<Agent>
     */
    private array $femmes;

    /**
     * @var list<Agent>
     */
    private array $hommes;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo(Carbon::parse('2026-11-18 08:15:00'));

        $this->edition = Edition::factory()->avecPhases()->enCours()->create([
            'annee' => 2026, 'date_debut' => '2026-11-01', 'date_fin' => '2026-12-31', 'date_gel' => '2026-10-01',
        ]);
        $dia = Structure::where('code', 'DIA')->firstOrFail();
        $depuis = Carbon::parse('2020-01-01');
        $this->femmes = Agent::factory()->femme()->affecteA($dia, $depuis)->count(3)->create()->all();
        $this->hommes = Agent::factory()->homme()->affecteA($dia, $depuis)->count(3)->create()->all();

        app(GenererScrutins::class)->handle($this->edition->phase(Phase::PRIMAIRES));
        $this->edition->phase(Phase::PRIMAIRES)->update(['statut' => StatutPhase::Ouverte]);
        $this->scrutin = Scrutin::where('code', 'STR-DIA')->firstOrFail();
        app(ArreterListes::class)->handle($this->scrutin);
        app(PlanifierScrutin::class)->handle($this->scrutin->fresh(), now()->subMinutes(15), now()->addHours(4));
        app(OuvrirScrutin::class)->handle($this->scrutin);
    }

    /**
     * R47, R48 : voix, pourcentage des exprimés, rang et lauréate ; procès-verbal provisoire.
     */
    public function test_r47_depouille_le_scrutin_et_designe_la_laureate(): void
    {
        $this->edition->update(['vote_blanc_autorise' => true]);
        $this->voter([0, 0, 0, 1, 2]);
        $this->voter([null]);

        $pv = $this->depouiller();

        $this->assertSame([6, 6, 1, 5], [$pv->nb_inscrits, $pv->nb_votants, $pv->nb_blancs, $pv->nb_exprimes]);
        $this->assertSame('100.00', $pv->taux_participation);
        $this->assertTrue($pv->quorum_atteint);
        $this->assertNull($pv->numero);

        $premiere = $this->candidate(0);
        $this->assertSame([3, '60.00', 1, true], [$premiere->nb_voix, $premiere->pourcentage, $premiere->rang, $premiere->est_laureate]);
        $this->assertSame([1, 2, false], [$this->candidate(1)->nb_voix, $this->candidate(1)->rang, $this->candidate(1)->est_laureate]);
        $this->assertSame(2, $this->candidate(2)->rang, 'Rang partagé à égalité de voix.');
    }

    /**
     * R33, R47 : une candidate désistée n'est pas classée.
     */
    public function test_r47_une_candidate_desistee_n_est_pas_classee(): void
    {
        DB::table('scrutins')->where('id', $this->scrutin->id)->update(['statut' => StatutScrutin::ListesArretees->value]);
        app(DesactiverCandidate::class)->handle($this->candidate(2));
        DB::table('scrutins')->where('id', $this->scrutin->id)->update(['statut' => StatutScrutin::Ouvert->value]);
        $this->voter([0, 1, 1]);

        $this->depouiller();

        $this->assertNull($this->candidate(2)->rang);
        $this->assertTrue($this->candidate(1)->est_laureate);
    }

    /**
     * R52 : un collège à trois élues désigne les trois premières ; une égalité à la troisième place est départagée par le comité.
     */
    public function test_r52_designe_autant_de_laureates_que_le_college_compte_d_elues(): void
    {
        $this->scrutin->college->update(['nb_elues' => 2]);
        $this->voter([0, 0, 0, 1, 1, 2]);

        $this->depouiller();

        $this->assertSame([$this->candidate(0)->id, $this->candidate(1)->id], $this->scrutin->laureates()->pluck('id')->all());
        $this->assertFalse($this->candidate(2)->est_laureate);
    }

    public function test_r52_une_egalite_a_la_derniere_place_d_elue_est_departagee(): void
    {
        $this->scrutin->college->update(['nb_elues' => 2]);
        $this->voter([0, 0, 1, 2]);

        $pv = $this->depouiller();

        $this->assertTrue($pv->egalite_a_departager);
        $this->assertSame([$this->candidate(0)->id], $this->scrutin->laureates()->pluck('id')->all(), 'La première est élue, la seconde place reste à départager.');
        app(TrancherEgalite::class)->handle($this->scrutin, [$this->candidate(2)], 'Décision du comité du 20 novembre');
        $this->assertEqualsCanonicalizing([$this->candidate(0)->id, $this->candidate(2)->id], $this->scrutin->laureates()->pluck('id')->all());
    }

    /**
     * R49 : une égalité en tête bloque la validation jusqu'au départage motivé du comité.
     */
    public function test_r49_une_egalite_en_tete_est_departagee_par_le_comite_avant_validation(): void
    {
        $this->voter([0, 0, 1, 1, 2]);

        $pv = $this->depouiller();
        $this->assertTrue($pv->egalite_a_departager);
        $this->assertSame(0, $this->scrutin->laureates()->count());
        $this->attendreErreur(fn () => app(ValiderResultats::class)->handle($this->scrutin), 'égalité');

        $this->attendreErreur(fn () => app(TrancherEgalite::class)->handle($this->scrutin, [$this->candidate(2)], 'Tirage au sort'), 'ex-æquo');
        $this->attendreErreur(fn () => app(TrancherEgalite::class)->handle($this->scrutin, [$this->candidate(0)], ' '), 'motivé');

        app(TrancherEgalite::class)->handle($this->scrutin, [$this->candidate(1)], 'Second tour du 20 novembre');

        $this->assertSame([$this->candidate(1)->id], $this->scrutin->laureates()->pluck('id')->all());
        $this->assertSame('Second tour du 20 novembre', $pv->fresh()->motif_departage);
        app(ValiderResultats::class)->handle($this->scrutin);
    }

    /**
     * R50 : quorum non atteint, le comité peut prolonger le scrutin.
     */
    public function test_r50_prolonge_un_scrutin_dont_le_quorum_n_est_pas_atteint(): void
    {
        $this->scrutin->update(['quorum' => 50]);
        $this->voter([0, 1]);

        $pv = $this->depouiller();
        $this->assertFalse($pv->quorum_atteint);

        app(ProlongerScrutin::class)->handle($this->scrutin, now()->addDay());

        $this->assertSame(StatutScrutin::Ouvert, $this->scrutin->fresh()->statut);
        $this->assertDatabaseMissing('proces_verbaux', ['scrutin_id' => $this->scrutin->id]);
        $this->assertNull($this->candidate(0)->nb_voix);
    }

    public function test_r50_refuse_de_prolonger_un_scrutin_dont_le_quorum_est_atteint(): void
    {
        $this->voter([0]);
        $this->depouiller();

        $this->attendreErreur(fn () => app(ProlongerScrutin::class)->handle($this->scrutin, now()->addDay()), 'quorum est atteint');
    }

    /**
     * R44 : pas de dépouillement si la chaîne des bulletins est rompue.
     */
    public function test_r44_refuse_de_depouiller_une_urne_alteree(): void
    {
        $this->voter([0]);
        DB::table('bulletins')->insert([
            'id' => (string) str()->uuid(), 'scrutin_id' => $this->scrutin->id, 'candidate_id' => $this->candidate(1)->id,
            'est_blanc' => false, 'date_heure' => now()->startOfHour(), 'empreinte' => str_repeat('c', 64), 'empreinte_precedente' => str_repeat('d', 64),
        ]);

        $this->attendreErreur(fn () => $this->depouiller(), 'rompue');
    }

    /**
     * R51, R54, R55 : validation numérotée, procès-verbal généré, publication, puis archives intangibles.
     */
    public function test_r51_valide_genere_le_proces_verbal_publie_et_archive_les_resultats(): void
    {
        $this->voter([0, 0, 1]);
        $this->depouiller();
        $comite = User::factory()->create();

        $pv = app(ValiderResultats::class)->handle($this->scrutin, $comite)->fresh();

        $this->assertSame('PV-2026-P1-001', $pv->numero);
        $this->assertTrue($pv->validePar->is($comite));
        $this->assertSame('proces-verbaux/PV-2026-P1-001.pdf', $pv->fichier);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($pv->fichier));
        $this->assertFalse(Storage::disk('local')->exists('proces-verbaux/PV-2026-P1-001.html'));
        $this->attendreErreur(fn () => $this->depouiller(), 'clos');

        app(PublierResultats::class)->handle($this->scrutin);

        $this->assertSame(StatutScrutin::ResultatsPublies, $this->scrutin->fresh()->statut);
        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('proces_verbaux')->where('id', $pv->id)->update(['nb_votants' => 99]));
    }

    public function test_r51_refuse_de_publier_avant_la_generation_du_proces_verbal(): void
    {
        Queue::fake();
        $this->voter([0]);
        $this->depouiller();
        app(ValiderResultats::class)->handle($this->scrutin);

        Queue::assertPushed(GenererFichierProcesVerbal::class);
        $this->attendreErreur(fn () => app(PublierResultats::class)->handle($this->scrutin), 'pas encore généré');
    }

    /**
     * R52, R53 : les lauréates publiées forment le palmarès ; celle de la finale est la Femme modèle du Trésor.
     */
    public function test_r53_proclame_la_femme_modele_et_etablit_le_palmares(): void
    {
        $this->voter([0, 0, 1]);
        $this->depouiller();
        app(ValiderResultats::class)->handle($this->scrutin);
        app(PublierResultats::class)->handle($this->scrutin);

        $finale = Scrutin::factory()->for($this->edition->phase(Phase::FINALE))->statut(StatutScrutin::ResultatsPublies)->create(['libelle' => 'Finale']);
        $gagnante = Candidate::factory()->for($finale)->for($this->femmes[0])->laureate()->create(['nb_voix' => 40]);

        $palmares = app(EtablirPalmares::class)->handle($this->edition);

        $this->assertCount(1, $palmares['femmes_modeles']);
        $this->assertTrue($palmares['femmes_modeles']->sole()->is($gagnante));
        $this->assertSame($this->femmes[0]->matricule, $palmares['phases'][0]['laureates'][0]['matricule']);
        $this->assertSame('Finale', $palmares['phases'][2]['laureates'][0]['scrutin']);
        $this->assertCount(0, $palmares['phases'][1]['laureates']);
    }

    /**
     * R53 : la lauréate de la dernière phase est prévenue par e-mail et voit sur son accueil qu'elle est Femme modèle du Trésor.
     */
    public function test_r53_previent_la_femme_modele_a_la_publication_de_la_finale(): void
    {
        Notification::fake();
        $this->femmes[0]->update(['email' => 'gagnante@example.test']);
        $finale = Scrutin::factory()->for($this->edition->phase(Phase::FINALE))->statut(StatutScrutin::ResultatsPublies)->create(['libelle' => 'Finale']);
        Candidate::factory()->for($finale)->for($this->femmes[0])->laureate()->create(['nb_voix' => 40]);

        (new EnvoyerQualifications($finale->id))->handle();

        Notification::assertSentOnDemandTimes(QualificationCandidate::class, 0);
        Notification::assertSentOnDemand(ProclamationFemmeModele::class, fn (ProclamationFemmeModele $notification, array $canaux, AnonymousNotifiable $destinataire): bool => $destinataire->routes['mail'] === 'gagnante@example.test'
            && $notification->toMail($destinataire)->subject === 'Vous êtes Femme modèle du Trésor 2026'
            && str_contains((string) $notification->toMail($destinataire)->render(), 'vous êtes élue Femme modèle du Trésor 2026'));

        $compte = User::factory()->avecRoles(CodeRole::Electeur)->pourAgent($this->femmes[0])->create();
        $this->withoutVite()->actingAs($compte)->get(route('espace'))->assertSee('Vous êtes Femme modèle du Trésor 2026')->assertSee('Finale');
    }

    /**
     * Fait voter un électeur par choix, dans l'ordre : index d'une femme candidate, ou null pour un vote blanc.
     *
     * @param  list<int|null>  $choix
     */
    /**
     * R35, R52 : à la publication, la lauréate est prévenue par e-mail de sa qualification et la voit sur son accueil.
     */
    public function test_r52_previent_la_laureate_de_sa_qualification_a_la_publication(): void
    {
        Notification::fake();
        $this->femmes[0]->update(['email' => 'laureate@example.test']);
        $this->femmes[1]->update(['email' => 'autre@example.test']);
        $this->voter([0, 0, 1]);
        $this->depouiller();
        app(ValiderResultats::class)->handle($this->scrutin, User::factory()->create());

        app(PublierResultats::class)->handle($this->scrutin);

        Notification::assertSentOnDemandTimes(QualificationCandidate::class, 1);
        Notification::assertSentOnDemand(QualificationCandidate::class, fn (QualificationCandidate $notification, array $canaux, AnonymousNotifiable $destinataire): bool => $destinataire->routes['mail'] === 'laureate@example.test'
            && $notification->phaseSuivante->numero === Phase::DEMI_FINALES
            && str_contains((string) $notification->toMail($destinataire)->render(), 'Vous êtes qualifiée pour la Phase 2 · demi-finales'));

        $compte = User::factory()->avecRoles(CodeRole::Electeur)->pourAgent($this->femmes[0])->create();
        $this->withoutVite()->actingAs($compte)->get(route('espace'))->assertSee('Vous êtes qualifiée pour la Phase 2 · demi-finales');
        $this->actingAs(User::factory()->avecRoles(CodeRole::Electeur)->pourAgent($this->femmes[1])->create())
            ->get(route('espace'))->assertDontSee('Vous êtes qualifiée');
    }

    private function voter(array $choix): void
    {
        $electeurs = Agent::whereIn('id', $this->scrutin->inscriptions()->select('agent_id'))
            ->whereNotIn('id', $this->scrutin->inscriptions()->where('a_vote', true)->select('agent_id'))
            ->orderBy('id')->get();

        foreach ($choix as $position => $indice) {
            app(DeposerBulletin::class)->handle($electeurs[$position], $this->scrutin, $indice === null ? null : $this->candidate($indice));
        }
    }

    private function depouiller(): ProcesVerbal
    {
        if ($this->scrutin->fresh()->statut === StatutScrutin::Ouvert) {
            $this->travelTo(Carbon::parse('2026-11-18 13:00:00'));
            app(FermerScrutin::class)->handle($this->scrutin);
        }

        return app(DepouillerScrutin::class)->handle($this->scrutin);
    }

    private function candidate(int $indice): Candidate
    {
        return $this->scrutin->candidates()->where('agent_id', $this->femmes[$indice]->id)->firstOrFail();
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
