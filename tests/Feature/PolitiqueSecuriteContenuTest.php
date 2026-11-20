<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PolitiqueSecuriteContenuTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_envoie_la_politique_en_mode_rapport_puis_bloquant(): void
    {
        config(['app.csp' => 'rapport']);
        $reponse = $this->get(route('login'))->assertOk()->assertHeaderMissing('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'self'", $reponse->headers->get('Content-Security-Policy-Report-Only'));
        $this->assertStringContainsString('report-uri /csp-rapport', $reponse->headers->get('Content-Security-Policy-Report-Only'));

        config(['app.csp' => 'bloquant']);
        $this->get(route('login'))->assertHeader('Content-Security-Policy')->assertHeaderMissing('Content-Security-Policy-Report-Only');

        Route::get('/essai-pdf', fn () => response('%PDF', 200, ['Content-Type' => 'application/pdf']))->middleware('web');
        $this->get('/essai-pdf')->assertHeaderMissing('Content-Security-Policy');

        config(['app.csp' => 'aucune']);
        $this->get(route('login'))->assertHeaderMissing('Content-Security-Policy')->assertHeaderMissing('Content-Security-Policy-Report-Only');
    }

    public function test_journalise_une_violation_signalee_sans_jeton_csrf(): void
    {
        Log::spy();

        $this->call('POST', route('csp.rapport'), [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], json_encode([
            'csp-report' => ['document-uri' => 'https://vote.test/espace', 'violated-directive' => 'script-src', 'blocked-uri' => 'https://exemple.test/x.js'],
        ]))->assertNoContent();

        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $contexte): bool => $contexte['blocked-uri'] === 'https://exemple.test/x.js');
    }
}
