<?php

namespace Tests\Feature\Suivi;

use App\Actions\Documents\CodeQr;
use App\Actions\Documents\LienVerification;
use App\Actions\Documents\LignesExport;
use App\Enums\Acces\CodeRole;
use App\Enums\Scrutins\StatutScrutin;
use App\Models\Agent;
use App\Models\Candidate;
use App\Models\Inscription;
use App\Models\ProcesVerbal;
use App\Models\Scrutin;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * QR code des documents PDF et page publique de vérification.
 */
class VerificationDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private Scrutin $scrutin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->scrutin = Scrutin::factory()->create(['libelle' => 'Scrutin de structure · DIA']);
        $dia = Structure::where('code', 'DIA')->firstOrFail();
        foreach (Agent::factory()->count(3)->create() as $agent) {
            Inscription::factory()->create(['scrutin_id' => $this->scrutin->id, 'agent_id' => $agent->id, 'structure_id' => $dia->id]);
        }
    }

    public function test_genere_un_qr_code_svg_integrable(): void
    {
        $qr = app(CodeQr::class)->dataUri('https://exemple.test/verification');

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $qr);
        $this->assertStringContainsString('<svg', base64_decode(substr($qr, strlen('data:image/svg+xml;base64,'))));
    }

    /**
     * Le PDF de la liste électorale porte un QR code qui renvoie vers une vérification conforme, puis « dépassée » si la liste change.
     */
    public function test_verifie_une_liste_electorale_puis_signale_un_changement(): void
    {
        $lignes = app(LignesExport::class)->listeElectorale($this->scrutin)['lignes'];
        $lien = app(LienVerification::class)->export('liste', $this->scrutin, app(LignesExport::class)->empreinte($lignes), count($lignes), Carbon::parse('2026-11-18 10:00'));

        $this->get($lien)->assertOk()->assertSee('Document authentique')->assertSee('Liste électorale')->assertSee('3')->assertDontSee('dépassé');

        Inscription::factory()->create(['scrutin_id' => $this->scrutin->id, 'structure_id' => Structure::where('code', 'DIA')->value('id')]);
        $this->get($lien)->assertOk()->assertSee('dépassé');
    }

    public function test_refuse_un_lien_de_verification_modifie(): void
    {
        $lien = app(LienVerification::class)->export('liste', $this->scrutin, str_repeat('a', 32), 3, now());

        $this->get(str_replace('lignes=3', 'lignes=4', $lien))->assertForbidden();
        $this->get(route('verification.export', ['type' => 'liste', 'scrutin' => $this->scrutin->id]))->assertForbidden();
    }

    /**
     * R46 : la vérification d'un procès-verbal ne montre les résultats qu'après leur publication.
     */
    public function test_verifie_un_proces_verbal_sans_reveler_les_resultats_avant_publication(): void
    {
        $laureate = Candidate::factory()->for($this->scrutin)->laureate()->create();
        $pv = ProcesVerbal::create([
            'scrutin_id' => $this->scrutin->id, 'numero' => 'PV-2026-P1-001', 'date_etablissement' => now(), 'date_validation' => now(),
            'nb_inscrits' => 3, 'nb_votants' => 2, 'nb_blancs' => 0, 'nb_exprimes' => 2, 'taux_participation' => 66.67,
        ]);
        $this->scrutin->update(['statut' => StatutScrutin::ResultatsValides]);
        $lien = app(LienVerification::class)->procesVerbal($pv);

        $this->get($lien)->assertOk()->assertSee('PV-2026-P1-001')->assertSee('résultats non encore publiés')->assertDontSee($laureate->agent->nom);

        $this->scrutin->update(['statut' => StatutScrutin::ResultatsPublies]);
        $pv->update(['date_publication' => now()]);
        $this->get($lien)->assertOk()->assertSee($laureate->agent->nom);
    }

    public function test_l_export_pdf_porte_l_en_tete_et_le_qr_code(): void
    {
        $comite = User::factory()->avecRoles(CodeRole::Comite)->create(['name' => 'KPADONOU Damien']);

        $reponse = $this->actingAs($comite)->get(route('exports.liste-electorale', [$this->scrutin, 'format' => 'pdf']));

        $reponse->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $reponse->getContent());
    }
}
