<?php

namespace Tests\Feature\Actions\Scrutins;

use App\Actions\Scrutins\ConstituerListes;
use App\Actions\Scrutins\GenererScrutins;
use App\Actions\Scrutins\RapportGeneration;
use App\Enums\Editions\StatutPhase;
use App\Enums\Referentiel\CodeTypeStructure;
use App\Enums\Scrutins\OrigineCandidate;
use App\Enums\Scrutins\StatutScrutin;
use App\Models\Affectation;
use App\Models\Agent;
use App\Models\Candidate;
use App\Models\Departement;
use App\Models\Edition;
use App\Models\Inscription;
use App\Models\Phase;
use App\Models\Scrutin;
use App\Models\Structure;
use Database\Seeders\ReferentielSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GenererScrutinsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = ReferentielSeeder::class;

    private Edition $edition;

    /**
     * @var array<string, Agent>
     */
    private array $agents = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->edition = Edition::factory()->avecPhases()->enCours()->create([
            'annee' => 2026, 'date_debut' => '2026-11-01', 'date_fin' => '2026-12-31', 'date_gel' => '2026-10-01',
        ]);

        $atl = Departement::where('code', 'ATL')->firstOrFail();
        $zou = Departement::where('code', 'ZOU')->firstOrFail();
        $tcOuidah = Structure::factory()->deType(CodeTypeStructure::TresorerieCommunale)->dansDepartement($atl)
            ->create(['code' => 'TC-OUIDAH', 'structure_parente_id' => $this->structure('TD-ATL')->id]);
        $antenneDiaZou = Structure::factory()->serviceDe($this->structure('DIA'))->dansDepartement($zou)->create(['code' => 'DIA-ZOU']);
        $serviceDr = Structure::factory()->serviceDe($this->structure('DR'))->create(['code' => 'DR-SERV']);

        $this->agents = [
            'femme_td_atl' => $this->agent('F', $this->structure('TD-ATL')),
            'homme_tc_ouidah' => $this->agent('M', $tcOuidah),
            'femme_antenne_dia_zou' => $this->agent('F', $antenneDiaZou),
            'femme_dia' => $this->agent('F', $this->structure('DIA')),
            'homme_dmg' => $this->agent('M', $this->structure('DMG')),
            'femme_service_dr' => $this->agent('F', $serviceDr),
            'arrivee_apres_gel' => $this->agent('F', $this->structure('DIA'), '2026-10-02'),
        ];

        // R15 : mutée à la TD du Zou après le gel, elle reste inscrite à la DIA.
        $mutee = $this->agent('F', $this->structure('DIA'), '2020-01-01', '2026-10-15');
        Affectation::factory()->for($mutee)->for($this->structure('TD-ZOU'))->create(['date_debut' => '2026-10-16']);
        $this->agents['mutee_apres_gel'] = $mutee;
    }

    /**
     * R20, R24, R25, R28, R37 : scrutins de la phase 1, circonscriptions, listes et candidates d'office.
     */
    public function test_r20_genere_les_scrutins_de_la_phase_1_pour_les_circonscriptions_comptant_une_femme(): void
    {
        $rapport = $this->generer(Phase::PRIMAIRES);

        $this->assertSame(['DEP-ATL', 'DEP-ZOU', 'STR-DIA', 'STR-DR'], $rapport->scrutins->pluck('code')->all());
        $this->assertContains('Scrutin de structure · Direction des Moyens généraux', $rapport->circonscriptionsSansFemme);
        $this->assertContains('Scrutin départemental · Borgou', $rapport->circonscriptionsSansFemme);

        // R24 : le scrutin départemental couvre la TD, les Trésoreries communales et les antennes du département.
        $this->assertEqualsCanonicalizing(['TD-ATL', 'TC-OUIDAH'], $this->scrutin('DEP-ATL')->structures->pluck('code')->all());
        $this->assertEqualsCanonicalizing(['TD-ZOU', 'DIA-ZOU'], $this->scrutin('DEP-ZOU')->structures->pluck('code')->all());
        $this->assertEqualsCanonicalizing(['DR', 'DR-SERV'], $this->scrutin('STR-DR')->structures->pluck('code')->all());

        $this->assertSame($this->ids('femme_td_atl', 'homme_tc_ouidah'), $this->inscrits('DEP-ATL'));
        $this->assertSame($this->ids('femme_antenne_dia_zou'), $this->inscrits('DEP-ZOU'), 'R25 : l’antenne vote avec son département.');
        $this->assertSame($this->ids('femme_dia', 'mutee_apres_gel'), $this->inscrits('STR-DIA'), 'R15, R29 : liste arrêtée à la date de gel.');

        // R28 : seules les électrices sont candidates d'office.
        $this->assertSame($this->ids('femme_td_atl'), $this->scrutin('DEP-ATL')->candidates->pluck('agent_id')->sort()->values()->all());
        $this->assertTrue($this->scrutin('DEP-ATL')->candidates->every(fn (Candidate $c) => $c->origine === OrigineCandidate::Automatique));

        // R37 : sans scrutin, les agents de la DMG ne votent pas en phase 1.
        $this->assertDatabaseMissing('inscriptions', ['agent_id' => $this->agents['homme_dmg']->id]);
    }

    /**
     * R21, R22, R26 : deux demi-finales par pôle, chaque agent inscrit une seule fois.
     */
    public function test_r21_genere_les_deux_demi_finales_et_inscrit_chaque_agent_une_fois(): void
    {
        $rapport = $this->generer(Phase::DEMI_FINALES);

        $this->assertSame(['DEMI-DEPARTEMENTAL', 'DEMI-STRUCTURES'], $rapport->scrutins->pluck('code')->all());
        $this->assertSame($this->ids('femme_td_atl', 'homme_tc_ouidah', 'femme_antenne_dia_zou'), $this->inscrits('DEMI-DEPARTEMENTAL'));
        $this->assertSame($this->ids('femme_dia', 'homme_dmg', 'femme_service_dr', 'mutee_apres_gel'), $this->inscrits('DEMI-STRUCTURES'));
        $this->assertSame(0, Candidate::count(), 'R35 : les candidates attendent la clôture de la phase 1.');
    }

    /**
     * R22, R23 : la finale réunit toute la DGTCP et ne relève d'aucun pôle.
     */
    public function test_r23_genere_la_finale_sur_toute_la_dgtcp(): void
    {
        $rapport = $this->generer(Phase::FINALE);

        $finale = $rapport->scrutins->sole();
        $this->assertSame('FINALE', $finale->code);
        $this->assertNull($finale->pole_electoral_id);
        $this->assertSame(Structure::actives()->count(), $finale->structures()->count());
        $this->assertCount(7, $this->inscrits('FINALE'), 'R29 : tous les agents sauf celle arrivée après le gel.');
        $this->assertNotContains($this->agents['arrivee_apres_gel']->id, $this->inscrits('FINALE'));
    }

    /**
     * R35 : en phase 2, les candidates sont les lauréates de la phase 1 de leur pôle, avec le lien vers leur candidature d'origine.
     */
    public function test_r35_reprend_les_laureates_qualifiees_de_la_phase_precedente(): void
    {
        $this->generer(Phase::PRIMAIRES);
        $this->generer(Phase::DEMI_FINALES);

        $laureateAtl = $this->scrutin('DEP-ATL')->candidates->sole();
        $laureateAtl->update(['est_laureate' => true, 'rang' => 1, 'nb_voix' => 1]);
        $laureateDia = $this->scrutin('STR-DIA')->candidates->firstWhere('agent_id', $this->agents['femme_dia']->id);
        $laureateDia->update(['est_laureate' => true, 'rang' => 1, 'nb_voix' => 2]);
        $this->edition->phase(Phase::PRIMAIRES)->scrutins()->update(['statut' => StatutScrutin::ResultatsPublies]);
        $this->edition->phase(Phase::PRIMAIRES)->update(['statut' => StatutPhase::Cloturee]);

        $demiDep = app(ConstituerListes::class)->handle($this->scrutin('DEMI-DEPARTEMENTAL'));
        app(ConstituerListes::class)->handle($this->scrutin('DEMI-STRUCTURES'));

        $candidate = $demiDep->candidates()->sole();
        $this->assertSame(OrigineCandidate::Qualification, $candidate->origine);
        $this->assertTrue($candidate->candidateSource->is($laureateAtl));
        $this->assertSame([$this->agents['femme_dia']->id], $this->scrutin('DEMI-STRUCTURES')->candidates->pluck('agent_id')->all());
    }

    public function test_refuse_de_generer_deux_fois_les_scrutins_d_une_phase(): void
    {
        $this->generer(Phase::PRIMAIRES);

        $this->expectException(ValidationException::class);

        $this->generer(Phase::PRIMAIRES);
    }

    private function generer(int $numero): RapportGeneration
    {
        return app(GenererScrutins::class)->handle($this->edition->phase($numero));
    }

    private function agent(string $sexe, Structure $structure, string $debut = '2020-01-01', ?string $fin = null): Agent
    {
        $agent = Agent::factory()->{$sexe === 'F' ? 'femme' : 'homme'}()->create();
        Affectation::factory()->for($agent)->for($structure)->create(['date_debut' => $debut, 'date_fin' => $fin]);

        return $agent;
    }

    private function structure(string $code): Structure
    {
        return Structure::where('code', $code)->firstOrFail();
    }

    private function scrutin(string $code): Scrutin
    {
        return Scrutin::where('code', $code)->with(['structures', 'candidates'])->firstOrFail();
    }

    /**
     * @return list<int>
     */
    private function inscrits(string $code): array
    {
        return Inscription::whereRelation('scrutin', 'code', $code)->orderBy('agent_id')->pluck('agent_id')->all();
    }

    /**
     * @return list<int>
     */
    private function ids(string ...$cles): array
    {
        return collect($cles)->map(fn (string $cle): int => $this->agents[$cle]->id)->sort()->values()->all();
    }
}
