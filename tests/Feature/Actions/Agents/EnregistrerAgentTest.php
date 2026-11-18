<?php

namespace Tests\Feature\Actions\Agents;

use App\Actions\Agents\EnregistrerAgent;
use App\Enums\Agents\Sexe;
use App\Models\Agent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EnregistrerAgentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * R11 : la fiche porte les informations utiles et, à titre informatif, les autres champs.
     */
    public function test_r11_cree_un_agent_avec_ses_informations_utiles_et_informatives(): void
    {
        $agent = $this->enregistrer([
            'matricule' => '102345',
            'npi' => '1234567890',
            'nom' => 'AHOUANSOU',
            'prenoms' => 'Clarisse',
            'sexe' => Sexe::Feminin,
            'date_naissance' => '1985-03-12',
            'fonction' => 'Chef service',
            'telephone' => '+229 01 00 00 00',
        ]);

        $this->assertTrue($agent->fresh()->estUneFemme());
        $this->assertSame('Chef service', $agent->fresh()->fonction);
    }

    /**
     * R11 : le sexe est obligatoire, il détermine la qualité de candidate.
     */
    public function test_r11_rejette_un_agent_sans_sexe(): void
    {
        $this->attendreErreur('sexe', fn () => $this->enregistrer([
            'matricule' => '102346',
            'nom' => 'DOSSOU',
            'prenoms' => 'Marc',
            'sexe' => '',
        ]));
    }

    /**
     * R11 : le matricule et le NPI sont uniques.
     */
    public function test_r11_rejette_un_matricule_ou_un_npi_deja_attribue(): void
    {
        $existant = Agent::factory()->create(['matricule' => '200001', 'npi' => '9999999999']);

        $this->attendreErreur('matricule', fn () => $this->enregistrer([
            'matricule' => '200001', 'nom' => 'X', 'prenoms' => 'Y', 'sexe' => 'M',
        ]));
        $this->attendreErreur('npi', fn () => $this->enregistrer([
            'matricule' => '200002', 'npi' => '9999999999', 'nom' => 'X', 'prenoms' => 'Y', 'sexe' => 'M',
        ]));

        $this->enregistrer([
            'matricule' => '200001', 'npi' => '9999999999', 'nom' => 'NOUVEAU NOM', 'prenoms' => 'Y', 'sexe' => 'M',
        ], $existant);
        $this->assertSame('NOUVEAU NOM', $existant->fresh()->nom);
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    private function enregistrer(array $donnees, ?Agent $agent = null): Agent
    {
        return app(EnregistrerAgent::class)->handle($donnees, $agent);
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
