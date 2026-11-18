<?php

namespace Tests\Feature\Http\Acces;

use App\Enums\Acces\CodeRole;
use App\Enums\Audit\ActionAuditee;
use App\Models\Agent;
use App\Models\CodeConnexion;
use App\Models\JournalAudit;
use App\Models\User;
use App\Notifications\CodeDeConnexion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Connexion sans mot de passe : matricule, puis code à usage unique reçu par e-mail (R1, R42).
 */
class ConnexionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Notification::fake();
    }

    public function test_un_visiteur_est_redirige_vers_la_connexion(): void
    {
        $this->get('/')->assertRedirect('/espace');
        $this->get('/espace')->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertSee('Matricule')->assertSee('Recevoir mon code')->assertDontSee('Mot de passe');
    }

    /**
     * R42 : le code est envoyé à l'adresse du compte, puis permet de se connecter une seule fois.
     */
    public function test_r42_connecte_par_le_code_recu_par_e_mail(): void
    {
        $utilisateur = User::factory()->avecRoles(CodeRole::Electeur)->create(['login' => '102345', 'email' => 'clarisse@example.test']);

        $this->post(route('login.store'), ['login' => '102345'])->assertRedirect(route('login.code'));
        $code = $this->codeEnvoyeA('clarisse@example.test');
        $this->get(route('login.code'))->assertOk()->assertSee('Matricule 102345');

        $this->post(route('login.verifier'), ['code' => $code])->assertRedirect(route('espace'));

        $this->assertAuthenticatedAs($utilisateur);
        $this->assertNotNull($utilisateur->fresh()->derniere_connexion);
        $this->assertNotSame($code, CodeConnexion::sole()->empreinte, 'Le code n’est stocké que sous forme d’empreinte.');

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->post(route('login.store'), ['login' => '102345']);
        $this->post(route('login.verifier'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    /**
     * Sans adresse sur le compte, le code part à l'adresse de la fiche agent.
     */
    public function test_envoie_le_code_a_l_adresse_de_la_fiche_agent(): void
    {
        $agent = Agent::factory()->create(['matricule' => '300001', 'email' => 'agent@example.test']);
        User::factory()->pourAgent($agent)->create(['email' => null]);

        $this->post(route('login.store'), ['login' => '300001']);

        $this->codeEnvoyeA('agent@example.test');
    }

    /**
     * R1 : rien n'est envoyé à un compte inconnu, désactivé ou sans adresse, et l'écran ne le révèle pas.
     */
    public function test_r1_n_envoie_rien_a_un_compte_inconnu_desactive_ou_sans_adresse(): void
    {
        User::factory()->inactif()->create(['login' => '200000', 'email' => 'inactif@example.test']);
        User::factory()->create(['login' => '200001', 'email' => null]);

        foreach (['999999', '200000', '200001'] as $login) {
            $this->post(route('login.store'), ['login' => $login])->assertRedirect(route('login.code'));
        }

        Notification::assertNothingSent();
        $this->assertSame(3, JournalAudit::where('action', ActionAuditee::ConnexionEchouee->value)->count());
    }

    public function test_refuse_un_code_faux_ou_expire_et_bloque_apres_cinq_essais(): void
    {
        User::factory()->create(['login' => '102345', 'email' => 'clarisse@example.test']);
        $this->post(route('login.store'), ['login' => '102345']);
        $code = $this->codeEnvoyeA('clarisse@example.test');

        foreach (range(1, 5) as $essai) {
            $this->post(route('login.verifier'), ['code' => '000000'])->assertSessionHasErrors('code');
        }
        $this->post(route('login.verifier'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post(route('login.renvoyer'))->assertSessionHas('status');
        $nouveau = $this->codeEnvoyeA('clarisse@example.test', 2);
        $this->travel(11)->minutes();
        $this->post(route('login.verifier'), ['code' => $nouveau])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_limite_les_demandes_de_code(): void
    {
        User::factory()->create(['login' => '102345', 'email' => 'clarisse@example.test']);

        foreach (range(1, 3) as $demande) {
            $this->post(route('login.store'), ['login' => '102345'])->assertSessionHasNoErrors();
        }

        $this->post(route('login.store'), ['login' => '102345'])->assertSessionHasErrors('login');
        Notification::assertSentTimes(CodeDeConnexion::class, 3);
    }

    public function test_la_page_du_code_exige_d_avoir_saisi_un_matricule(): void
    {
        $this->get(route('login.code'))->assertRedirect(route('login'));
        $this->post(route('login.verifier'), ['code' => '123456'])->assertRedirect(route('login'));
    }

    /**
     * Récupère le code du dernier e-mail envoyé à l'adresse donnée.
     */
    private function codeEnvoyeA(string $email, int $nombre = 1): string
    {
        $codes = [];
        Notification::assertSentTimes(CodeDeConnexion::class, $nombre);
        Notification::assertSentTo(new AnonymousNotifiable, CodeDeConnexion::class, function (CodeDeConnexion $notification, array $canaux, AnonymousNotifiable $destinataire) use ($email, &$codes): bool {
            if ($destinataire->routes['mail'] !== $email) {
                return false;
            }
            $codes[] = $notification->code;

            return true;
        });

        return end($codes);
    }
}
