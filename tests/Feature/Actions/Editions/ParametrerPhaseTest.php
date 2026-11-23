<?php

namespace Tests\Feature\Actions\Editions;

use App\Actions\Editions\ParametrerPhase;
use App\Enums\Editions\StatutPhase;
use App\Models\Edition;
use App\Models\Phase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ParametrerPhaseTest extends TestCase
{
    use RefreshDatabase;

    private Edition $edition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->edition = Edition::factory()->avecPhases()->create([
            'annee' => 2027,
            'date_debut' => '2027-03-01',
            'date_fin' => '2027-04-30',
            'date_gel' => '2027-02-15',
        ]);
    }

    /**
     * R17 : une phase planifiée reçoit ses dates, dans le calendrier de l'édition.
     */
    public function test_r17_planifie_une_phase(): void
    {
        $phase = $this->parametrer(1, '2027-03-01', '2027-03-15');

        $this->assertSame('2027-03-01', $phase->fresh()->date_debut->toDateString());
        $this->assertSame('2027-03-15', $phase->fresh()->date_fin->toDateString());
    }

    /**
     * R17 : les phases sont ordonnées et ne se chevauchent pas.
     */
    public function test_r17_rejette_une_phase_qui_chevauche_la_precedente_ou_la_suivante(): void
    {
        $this->parametrer(1, '2027-03-01', '2027-03-15');
        $this->parametrer(3, '2027-04-15', '2027-04-30');

        $this->attendreErreur('phase', fn () => $this->parametrer(2, '2027-03-15', '2027-03-30'));
        $this->attendreErreur('phase', fn () => $this->parametrer(2, '2027-03-20', '2027-04-15'));
        $this->parametrer(2, '2027-03-20', '2027-04-10');
    }

    public function test_rejette_une_phase_hors_du_calendrier_de_l_edition_ou_avant_le_gel(): void
    {
        $this->attendreErreur('phase', fn () => $this->parametrer(3, '2027-04-20', '2027-05-10'));

        $this->edition->update(['date_gel' => '2027-03-01']);
        $this->attendreErreur('phase', fn () => $this->parametrer(1, '2027-03-01', '2027-03-10'));
    }

    public function test_refuse_de_replanifier_une_phase_ouverte(): void
    {
        $this->edition->phase(1)->update(['statut' => StatutPhase::Ouverte]);

        $this->attendreErreur('phase', fn () => $this->parametrer(1, '2027-03-02', '2027-03-15'));
    }

    private function parametrer(int $numero, string $debut, string $fin): Phase
    {
        $phase = $this->edition->phases()->where('numero', $numero)->firstOrFail();

        return app(ParametrerPhase::class)->handle($phase, ['date_debut' => $debut, 'date_fin' => $fin]);
    }

    private function attendreErreur(string $champ, callable $action): void
    {
        try {
            $action();
            $this->fail("Une erreur de validation était attendue sur « {$champ} ».");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($champ, $exception->errors());
        }
    }
}
