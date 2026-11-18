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
     * R52 : le nombre de qualifiées par scrutin est un paramètre de la phase.
     */
    public function test_r52_planifie_une_phase_et_fixe_son_nombre_de_qualifiees(): void
    {
        $phase = $this->parametrer(1, '2027-03-01', '2027-03-15', 2);

        $this->assertSame('2027-03-15', $phase->fresh()->date_fin->toDateString());
        $this->assertSame(2, $phase->fresh()->nb_qualifiees);
    }

    /**
     * R17 : les phases sont ordonnées et ne se chevauchent pas.
     */
    public function test_r17_rejette_une_phase_qui_chevauche_la_precedente_ou_la_suivante(): void
    {
        $this->parametrer(1, '2027-03-01', '2027-03-15');
        $this->parametrer(3, '2027-04-15', '2027-04-30');

        $this->attendreErreur('date_debut', fn () => $this->parametrer(2, '2027-03-15', '2027-03-30'));
        $this->attendreErreur('date_fin', fn () => $this->parametrer(2, '2027-03-20', '2027-04-15'));
        $this->parametrer(2, '2027-03-20', '2027-04-10');
    }

    public function test_rejette_une_phase_hors_du_calendrier_de_l_edition_ou_avant_le_gel(): void
    {
        $this->attendreErreur('date_debut', fn () => $this->parametrer(3, '2027-04-20', '2027-05-10'));

        $this->edition->update(['date_gel' => '2027-03-01']);
        $this->attendreErreur('date_debut', fn () => $this->parametrer(1, '2027-03-01', '2027-03-10'));
    }

    public function test_refuse_de_replanifier_une_phase_ouverte(): void
    {
        $this->edition->phase(1)->update(['statut' => StatutPhase::Ouverte]);

        $this->attendreErreur('phase', fn () => $this->parametrer(1, '2027-03-02', '2027-03-15'));
    }

    private function parametrer(int $numero, string $debut, string $fin, ?int $nbQualifiees = null): Phase
    {
        $phase = $this->edition->phases()->where('numero', $numero)->firstOrFail();

        return app(ParametrerPhase::class)->handle($phase, array_filter([
            'date_debut' => $debut,
            'date_fin' => $fin,
            'nb_qualifiees' => $nbQualifiees,
        ]));
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
