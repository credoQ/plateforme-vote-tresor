<?php

namespace Tests\Feature\Actions\Agents;

use App\Actions\Agents\ImporterAgents;
use App\Actions\Agents\RapportImport;
use App\Models\Agent;
use App\Models\Structure;
use Database\Seeders\ReferentielSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class ImporterAgentsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = ReferentielSeeder::class;

    private const ENTETE = 'Matricule;NPI;Nom;Prénoms;Sexe;Date naissance;Structure;Date début;Fonction';

    public function test_importe_les_agents_et_leurs_affectations(): void
    {
        $rapport = $this->importer([
            '100001;1111111111;AHOUANSOU;Clarisse;F;12/03/1985;DIA;2021-01-04;Chef service',
            '100002;2222222222;DOSSOU;Marc;Masculin;1979-07-01;td-zou;;',
        ]);

        $this->assertSame(2, $rapport->agentsCrees);
        $this->assertSame(2, $rapport->affectationsCreees);
        $this->assertSame([], $rapport->rejets);

        $clarisse = Agent::where('matricule', '100001')->firstOrFail();
        $this->assertTrue($clarisse->estUneFemme());
        $this->assertSame('1985-03-12', $clarisse->date_naissance->toDateString());
        $this->assertSame('DIA', $clarisse->affectationLe()->structure->code);
        $this->assertSame('2026-01-15', Agent::where('matricule', '100002')->firstOrFail()->affectations()->first()->date_debut->toDateString());
    }

    /**
     * R11 à R13 : les lignes incomplètes ou en doublon sont rejetées sans bloquer les autres.
     */
    public function test_rejette_les_doublons_et_les_lignes_incompletes(): void
    {
        $rapport = $this->importer([
            '100001;1111111111;AHOUANSOU;Clarisse;F;;DIA;;',
            '100001;3333333333;AHOUANSOU;Clarisse;F;;DIA;;',
            '100003;;KPADE;Inès;;;DIA;;',
            '100004;;HOUNTON;Paul;M;;;;',
            '100005;;ZINSOU;Rose;F;;INCONNUE;;',
            '100006;1111111111;AGBO;Léa;F;;DR;;',
        ]);

        $this->assertSame(1, $rapport->agentsCrees);
        $this->assertSame([3, 4, 5, 6, 7], array_keys($rapport->rejets));
        $this->assertStringContainsString('doublon', $rapport->rejets[3][0]);
        $this->assertStringContainsString('Sexe absent', $rapport->rejets[4][0]);
        $this->assertStringContainsString('absente', $rapport->rejets[5][0]);
        $this->assertStringContainsString('inconnue', $rapport->rejets[6][0]);
        $this->assertStringContainsString('NPI', $rapport->rejets[7][0]);
        $this->assertDatabaseMissing('agents', ['matricule' => '100006']);
    }

    /**
     * R12 : un nouvel import met à jour la fiche et enregistre la mutation dans l'historique.
     */
    public function test_r12_un_nouvel_import_met_a_jour_l_agent_et_enregistre_sa_mutation(): void
    {
        $this->importer(['100001;1111111111;AHOUANSOU;Clarisse;F;;DIA;2021-01-04;']);

        $rapport = $this->importer(['100001;1111111111;AHOUANSOU épouse GBAGUIDI;Clarisse;F;;TD-ATL;2026-02-01;']);

        $agent = Agent::where('matricule', '100001')->firstOrFail();
        $this->assertSame(1, $rapport->agentsMisAJour);
        $this->assertSame('AHOUANSOU épouse GBAGUIDI', $agent->nom);
        $this->assertSame(['TD-ATL', 'DIA'], $agent->affectations()->with('structure')->get()->pluck('structure.code')->all());
    }

    public function test_refuse_un_fichier_sans_colonne_obligatoire(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->importer(['100001;AHOUANSOU'], 'Matricule;Nom');
    }

    public function test_la_commande_affiche_le_bilan_et_echoue_s_il_y_a_des_rejets(): void
    {
        $chemin = $this->fichier(['100001;;AHOUANSOU;Clarisse;F;;DIA;;', '100002;;DOSSOU;Marc;;;DIA;;']);

        $this->artisan('agents:importer', ['fichier' => $chemin, '--date' => '2026-01-15'])
            ->expectsOutputToContain('Lignes rejetées')
            ->assertFailed();

        $this->assertTrue(Structure::where('code', 'DIA')->exists());
        $this->assertDatabaseHas('agents', ['matricule' => '100001']);
    }

    /**
     * @param  list<string>  $lignes
     */
    private function importer(array $lignes, string $entete = self::ENTETE): RapportImport
    {
        return app(ImporterAgents::class)->handle($this->fichier($lignes, $entete), Carbon::parse('2026-01-15'));
    }

    /**
     * @param  list<string>  $lignes
     */
    private function fichier(array $lignes, string $entete = self::ENTETE): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'agents');
        file_put_contents($chemin, "\xEF\xBB\xBF".implode("\n", [$entete, ...$lignes])."\n");
        $this->beforeApplicationDestroyed(fn () => @unlink($chemin));

        return $chemin;
    }
}
