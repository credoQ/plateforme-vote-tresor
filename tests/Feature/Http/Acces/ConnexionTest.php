<?php

namespace Tests\Feature\Http\Acces;

use App\Enums\Acces\CodeRole;
use App\Enums\Audit\ActionAuditee;
use App\Livewire\Acces\Connexion;
use App\Models\Agent;
use App\Models\CodeConnexion;
use App\Models\JournalAudit;
use App\Models\User;
use App\Notifications\CodeDeConnexion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
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

    public function test_la_page_de_connexion_propose_les_videos_de_presentation(): void
    {
        $reponse = $this->get(route('login'))->assertOk();

        foreach (config('videos') as $video) {
            $this->assertFileExists(public_path("videos/{$video['fichier']}.mp4"));
            $this->assertFileExists(public_path("videos/{$video['fichier']}.jpg"));
            $reponse->assertSee($video['titre'])->assertSee(asset("videos/{$video['fichier']}.jpg"), false);
        }
    }

    public function test_ne_pose_ni_cookie_xsrf_ni_cookie_de_session_au_nom_du_cadriciel(): void
    {
        config(['session.cookie' => 'fm_sid']);

        $this->get(route('login'))->assertOk()->assertCookieMissing('XSRF-TOKEN')->assertCookie('fm_sid');
    }

    public function test_seule_la_page_de_connexion_est_indexable_et_partageable(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSee('<meta property="og:image" content="'.asset('images/partage.png').'">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
            ->assertSee('<link rel="icon" href="'.asset('favicon.ico').'" sizes="48x48">', false)
            ->assertSeeLivewire(Connexion::class);
    }

    public function test_un_profil_de_gestion_arrive_sur_la_console_apres_la_connexion(): void
    {
        User::factory()->avecRoles(CodeRole::Comite)->create(['login' => 'COMITE01', 'email' => 'comite@example.test']);

        $connexion = Livewire::test(Connexion::class)->set('login', 'COMITE01')->call('envoyer');
        $code = $this->codeEnvoyeA('comite@example.test');

        $connexion->set('code', $code)->call('verifier')->assertRedirect(route('tableau-de-bord'));
    }

    /**
     * R42 : le code est envoyé à l'adresse du compte, puis permet de se connecter une seule fois.
     */
    public function test_r42_connecte_par_le_code_recu_par_e_mail(): void
    {
        $utilisateur = User::factory()->avecRoles(CodeRole::Electeur)->create(['login' => '102345', 'email' => 'clarisse@example.test']);

        $connexion = Livewire::test(Connexion::class)->set('login', '102345')->call('envoyer')
            ->assertHasNoErrors()->assertSet('etape', 'code')->assertSee('(matricule 102345)');
        $code = $this->codeEnvoyeA('clarisse@example.test');

        $connexion->set('code', $code)->call('verifier')->assertRedirect(route('espace'));

        $this->assertAuthenticatedAs($utilisateur);
        $this->assertNotNull($utilisateur->fresh()->derniere_connexion);
        $this->assertNotSame($code, CodeConnexion::sole()->empreinte, 'Le code n’est stocké que sous forme d’empreinte.');

        $this->post(route('logout'))->assertRedirect(route('login'));
        Livewire::test(Connexion::class)->set('login', '102345')->call('envoyer')
            ->set('code', $code)->call('verifier')->assertHasErrors('code');
        $this->assertGuest();
    }

    /**
     * Sans adresse sur le compte, le code part à l'adresse de la fiche agent.
     */
    public function test_envoie_le_code_a_l_adresse_de_la_fiche_agent(): void
    {
        $agent = Agent::factory()->create(['matricule' => '300001', 'email' => 'agent@example.test']);
        User::factory()->pourAgent($agent)->create(['email' => null]);

        Livewire::test(Connexion::class)->set('login', '300001')->call('envoyer');

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
            Livewire::test(Connexion::class)->call('changerDeMatricule')->set('login', $login)->call('envoyer')
                ->assertHasNoErrors()->assertSet('etape', 'code');
        }

        Notification::assertNothingSent();
        $this->assertSame(3, JournalAudit::where('action', ActionAuditee::ConnexionEchouee->value)->count());
    }

    public function test_refuse_un_code_faux_ou_expire_et_bloque_apres_cinq_essais(): void
    {
        User::factory()->create(['login' => '102345', 'email' => 'clarisse@example.test']);
        $connexion = Livewire::test(Connexion::class)->set('login', '102345')->call('envoyer');
        $code = $this->codeEnvoyeA('clarisse@example.test');

        foreach (range(1, 5) as $essai) {
            $connexion->set('code', '000000')->call('verifier')->assertHasErrors('code');
        }
        $connexion->set('code', $code)->call('verifier')->assertHasErrors('code');
        $this->assertGuest();

        $this->travel(1)->minutes();
        $connexion->call('renvoyer')->assertHasNoErrors()->assertDispatched('notifier');
        $nouveau = $this->codeEnvoyeA('clarisse@example.test', 2);
        $this->travel(11)->minutes();
        $connexion->set('code', $nouveau)->call('verifier')->assertHasErrors('code');
        $this->assertGuest();
    }

    public function test_un_nouveau_code_ne_se_demande_qu_une_minute_apres_le_precedent(): void
    {
        User::factory()->create(['login' => '102345', 'email' => 'clarisse@example.test']);
        $connexion = Livewire::test(Connexion::class)->set('login', '102345')->call('envoyer')
            ->assertDispatched('code-envoye', attente: Connexion::DELAI_RENVOI);

        $this->travel(20)->seconds();
        $connexion->call('renvoyer')->assertHasErrors(['code' => 'Patientez encore 40 seconde(s) avant de demander un nouveau code.']);
        $this->codeEnvoyeA('clarisse@example.test', 1);

        $this->travel(40)->seconds();
        $connexion->call('renvoyer')->assertHasNoErrors()->assertDispatched('notifier');
        $this->codeEnvoyeA('clarisse@example.test', 2);
    }

    public function test_limite_les_demandes_de_code(): void
    {
        User::factory()->create(['login' => '102345', 'email' => 'clarisse@example.test']);
        $connexion = Livewire::test(Connexion::class);

        foreach (range(1, 3) as $demande) {
            $connexion->call('changerDeMatricule')->set('login', '102345')->call('envoyer')->assertHasNoErrors();
        }

        $connexion->call('changerDeMatricule')->set('login', '102345')->call('envoyer')->assertHasErrors('login')->assertSet('etape', 'matricule');
        Notification::assertSentTimes(CodeDeConnexion::class, 3);
    }

    /**
     * R42 : la saisie du code est limitée par matricule ; des agents qui partagent une adresse IP ne se bloquent pas.
     */
    public function test_r42_limite_la_saisie_du_code_par_matricule_et_non_par_adresse_ip(): void
    {
        session(['connexion.login' => '102345']);
        $connexion = Livewire::test(Connexion::class)->assertSet('etape', 'code');
        foreach (range(1, 10) as $essai) {
            $connexion->set('code', '000000')->call('verifier')->assertHasErrors(['code' => 'Code incorrect ou expiré. Demandez un nouveau code si besoin.']);
        }
        $connexion->set('code', '000000')->call('verifier')->assertHasErrors(['code' => 'Trop d’essais. Réessayez dans une minute.']);

        session(['connexion.login' => '106789']);
        Livewire::test(Connexion::class)->set('code', '000000')->call('verifier')
            ->assertHasErrors(['code' => 'Code incorrect ou expiré. Demandez un nouveau code si besoin.']);
    }

    public function test_la_saisie_du_code_exige_d_avoir_saisi_un_matricule(): void
    {
        Livewire::test(Connexion::class)->assertSet('etape', 'matricule')
            ->set('etape', 'code')->set('code', '123456')->call('verifier')
            ->assertSet('etape', 'matricule');
        $this->assertGuest();
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
