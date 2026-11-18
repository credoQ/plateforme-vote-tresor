<?php

namespace Tests\Feature\Http\Acces;

use App\Enums\Acces\CodeRole;
use App\Enums\Audit\ActionAuditee;
use App\Models\JournalAudit;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Authentification par Laravel Fortify (R1, R42).
 */
class ConnexionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_un_visiteur_est_redirige_vers_la_connexion(): void
    {
        $this->get('/')->assertRedirect('/espace');
        $this->get('/espace')->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertSee('Identifiez-vous avec votre matricule')->assertSee('Mot de passe oublié ?');
    }

    /**
     * R1, R42 : connexion par matricule et mot de passe ; la date de dernière connexion est enregistrée.
     */
    public function test_r42_connecte_un_agent_par_son_matricule(): void
    {
        $utilisateur = User::factory()->avecRoles(CodeRole::Electeur)->create(['login' => '102345', 'password' => 'secret-2026']);

        $this->post(route('login.store'), ['login' => '102345', 'password' => 'secret-2026'])->assertRedirect('/espace');

        $this->assertAuthenticatedAs($utilisateur);
        $this->assertNotNull($utilisateur->fresh()->derniere_connexion);
        $this->get(route('espace'))->assertOk()->assertSee($utilisateur->name);
    }

    /**
     * R1 : un compte désactivé ne se connecte pas, même avec le bon mot de passe.
     */
    public function test_r1_refuse_un_mauvais_mot_de_passe_et_un_compte_desactive(): void
    {
        User::factory()->create(['login' => '102345', 'password' => 'secret-2026']);
        User::factory()->inactif()->create(['login' => '200000', 'password' => 'secret-2026']);

        $this->post(route('login.store'), ['login' => '102345', 'password' => 'mauvais'])
            ->assertSessionHasErrors(['login' => 'Matricule ou mot de passe incorrect, ou compte désactivé.']);
        $this->post(route('login.store'), ['login' => '200000', 'password' => 'secret-2026'])->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_bloque_la_connexion_apres_cinq_echecs(): void
    {
        User::factory()->create(['login' => '102345', 'password' => 'secret-2026']);
        $this->freezeSecond();

        foreach (range(1, 5) as $essai) {
            $this->post(route('login.store'), ['login' => '102345', 'password' => 'mauvais']);
        }

        $this->post(route('login.store'), ['login' => '102345', 'password' => 'secret-2026'])->assertStatus(429);
        $this->assertGuest();
    }

    /**
     * Le mot de passe provisoire doit être changé avant tout autre écran.
     */
    public function test_impose_le_changement_du_mot_de_passe_provisoire(): void
    {
        $utilisateur = User::factory()->create(['password' => 'Provisoire1', 'mot_de_passe_a_changer' => true]);

        $this->actingAs($utilisateur)->get(route('espace'))->assertRedirect(route('securite'));
        $this->get(route('securite'))->assertOk()->assertSee('Première connexion')->assertDontSee('Gérer la double authentification');

        $this->put(route('user-password.update'), [
            'current_password' => 'mauvais', 'password' => 'Nouveau-2026', 'password_confirmation' => 'Nouveau-2026',
        ])->assertSessionHasErrors(['current_password' => 'Le mot de passe actuel est incorrect.']);
        $this->put(route('user-password.update'), [
            'current_password' => 'Provisoire1', 'password' => 'court1', 'password_confirmation' => 'court1',
        ])->assertSessionHasErrors('password');

        $this->put(route('user-password.update'), [
            'current_password' => 'Provisoire1', 'password' => 'Nouveau-2026', 'password_confirmation' => 'Nouveau-2026',
        ])->assertSessionHasNoErrors()->assertSessionHas('status', 'password-updated');

        $utilisateur->refresh();
        $this->assertFalse($utilisateur->mot_de_passe_a_changer);
        $this->assertTrue(Hash::check('Nouveau-2026', $utilisateur->password));
        $this->get(route('espace'))->assertOk();
    }

    public function test_deconnecte_l_utilisateur(): void
    {
        $this->actingAs(User::factory()->create())->post(route('logout'))->assertRedirect('/');

        $this->assertGuest();
    }

    /**
     * Mot de passe oublié : un lien est envoyé par e-mail, le nouveau mot de passe est définitif et la réinitialisation journalisée.
     */
    public function test_reinitialise_un_mot_de_passe_oublie_par_e_mail(): void
    {
        Notification::fake();
        $utilisateur = User::factory()->create(['email' => 'clarisse@example.test', 'password' => 'Ancien-2026', 'mot_de_passe_a_changer' => true]);

        $this->get(route('password.request'))->assertOk()->assertSee('Recevoir le lien');
        $this->post(route('password.email'), ['email' => 'clarisse@example.test'])->assertSessionHas('status');

        $jeton = null;
        Notification::assertSentTo($utilisateur, ResetPassword::class, function (ResetPassword $notification) use (&$jeton, $utilisateur): bool {
            $jeton = $notification->token;
            $this->assertStringContainsString('Choisir un nouveau mot de passe', $notification->toMail($utilisateur)->render());

            return true;
        });

        $this->get(route('password.reset', ['token' => $jeton, 'email' => 'clarisse@example.test']))->assertOk()->assertSee('Nouveau mot de passe');
        $this->post(route('password.update'), [
            'token' => $jeton, 'email' => 'clarisse@example.test', 'password' => 'Nouveau-2026', 'password_confirmation' => 'Nouveau-2026',
        ])->assertRedirect(route('login'));

        $utilisateur->refresh();
        $this->assertTrue(Hash::check('Nouveau-2026', $utilisateur->password));
        $this->assertFalse($utilisateur->mot_de_passe_a_changer);
        $this->assertSame(1, JournalAudit::where('action', ActionAuditee::MotDePasseReinitialise->value)->count());
    }

    /**
     * Double authentification facultative : activation confirmée par un code, puis code demandé à la connexion.
     */
    public function test_active_la_double_authentification_puis_l_exige_a_la_connexion(): void
    {
        $utilisateur = User::factory()->create(['login' => 'comite', 'password' => 'secret-2026']);
        $totp = new Google2FA;

        $this->actingAs($utilisateur)->get(route('securite.double-authentification'))->assertRedirect(route('password.confirm'));
        $this->withSession(['auth.password_confirmed_at' => time()]);
        $this->get(route('securite.double-authentification'))->assertOk()->assertSee('Activer la double authentification');

        $this->post(route('two-factor.enable'))->assertSessionHasNoErrors();
        $secret = decrypt($utilisateur->fresh()->two_factor_secret);
        $this->get(route('securite.double-authentification'))->assertSee('Confirmer l’activation');
        $this->post(route('two-factor.confirm'), ['code' => $totp->getCurrentOtp($secret)])->assertSessionHasNoErrors();
        $this->assertTrue($utilisateur->fresh()->hasEnabledTwoFactorAuthentication());

        $this->post(route('logout'));
        $this->post(route('login.store'), ['login' => 'comite', 'password' => 'secret-2026'])->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
        $this->get(route('two-factor.login'))->assertOk()->assertSee('Utiliser un code de secours');

        $this->post(route('two-factor.login.store'), ['code' => '000000'])->assertRedirect(route('two-factor.login'));
        $codeDeSecours = $utilisateur->fresh()->recoveryCodes()[0];
        $this->post(route('two-factor.login.store'), ['recovery_code' => $codeDeSecours])->assertRedirect('/espace');
        $this->assertAuthenticatedAs($utilisateur);
        $this->assertNotContains($codeDeSecours, $utilisateur->fresh()->recoveryCodes(), 'Un code de secours ne sert qu’une fois.');
    }
}
