<?php

namespace Tests\Feature\Actions\Agents;

use App\Actions\Agents\EnregistrerAgent;
use App\Enums\Acces\CodeRole;
use App\Enums\Agents\Sexe;
use App\Models\Agent;
use App\Models\User;
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
            'email' => 'clarisse@example.test',
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
     * R11 : l'adresse e-mail est facultative (connexion par code imprimé), mais valide quand elle est saisie.
     */
    public function test_r11_l_adresse_e_mail_est_facultative_mais_valide(): void
    {
        $this->attendreErreur('email', fn () => $this->enregistrer(['matricule' => '102347', 'nom' => 'DOSSOU', 'prenoms' => 'Marc', 'sexe' => 'M', 'email' => 'marc.dossou']));
        $this->assertDatabaseMissing('agents', ['matricule' => '102347']);

        $this->assertNull($this->enregistrer(['matricule' => '102347', 'nom' => 'DOSSOU', 'prenoms' => 'Marc', 'sexe' => 'M'])->email);
    }

    /**
     * R11 : le matricule et le NPI sont uniques.
     */
    public function test_r11_rejette_un_matricule_ou_un_npi_deja_attribue(): void
    {
        $existant = Agent::factory()->create(['matricule' => '200001', 'npi' => '9999999999']);

        $this->attendreErreur('matricule', fn () => $this->enregistrer([
            'matricule' => '200001', 'nom' => 'X', 'prenoms' => 'Y', 'sexe' => 'M', 'email' => 'x@example.test',
        ]));
        $this->attendreErreur('npi', fn () => $this->enregistrer([
            'matricule' => '200002', 'npi' => '9999999999', 'nom' => 'X', 'prenoms' => 'Y', 'sexe' => 'M', 'email' => 'x@example.test',
        ]));

        $this->enregistrer([
            'matricule' => '200001', 'npi' => '9999999999', 'nom' => 'NOUVEAU NOM', 'prenoms' => 'Y', 'sexe' => 'M', 'email' => 'x@example.test',
        ], $existant);
        $this->assertSame('NOUVEAU NOM', $existant->fresh()->nom);
    }

    /**
     * R3 : un nouvel agent reçoit aussitôt un compte électeur identifié par son matricule ; sa mise à jour n'en ouvre pas d'autre.
     */
    public function test_r3_un_nouvel_agent_recoit_son_compte_electeur(): void
    {
        $agent = $this->enregistrer(['matricule' => '300001', 'nom' => 'SAGBO', 'prenoms' => 'Rachelle', 'sexe' => 'F', 'email' => 'rachelle@example.test']);

        $compte = $agent->compte;
        $this->assertNotNull($compte);
        $this->assertSame('300001', $compte->login);
        $this->assertSame('SAGBO Rachelle', $compte->name);
        $this->assertTrue($compte->aLeRole(CodeRole::Electeur));

        $this->enregistrer(['matricule' => '300001', 'nom' => 'SAGBO', 'prenoms' => 'Rachelle Afi', 'sexe' => 'F', 'email' => 'rachelle@example.test'], $agent);
        $this->assertSame(1, User::where('agent_id', $agent->id)->count());
    }

    /**
     * R3 : un compte déjà ouvert sous ce matricule, sans agent, est rattaché au nouvel agent au lieu d'être doublé.
     */
    public function test_r3_rattache_le_compte_existant_du_matricule(): void
    {
        $administrateur = User::factory()->avecRoles(CodeRole::Administrateur)->create(['login' => '300002']);

        $agent = $this->enregistrer(['matricule' => '300002', 'nom' => 'KPADE', 'prenoms' => 'Inès', 'sexe' => 'F', 'email' => 'ines@example.test']);

        $administrateur->refresh();
        $this->assertSame($agent->id, $administrateur->agent_id);
        $this->assertTrue($administrateur->aLeRole(CodeRole::Administrateur));
        $this->assertTrue($administrateur->aLeRole(CodeRole::Electeur));
        $this->assertSame(1, User::where('login', '300002')->count());
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
