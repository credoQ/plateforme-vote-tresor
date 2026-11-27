<?php

namespace Tests\Feature\Http\Acces;

use App\Actions\Acces\DemanderCodeConnexion;
use App\Actions\Acces\GenererCodesAcces;
use App\Actions\Acces\ImprimerCodesAcces;
use App\Enums\Acces\CodeRole;
use App\Enums\Audit\ActionAuditee;
use App\Livewire\Acces\CodesAcces;
use App\Livewire\Acces\Connexion;
use App\Models\Agent;
use App\Models\JournalAudit;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Mode de connexion « code imprimé » (CONNEXION_MODE=code_imprime), exception à R42 : un code personnel par compte,
 * remis sur un coupon par le comité, valable pour toute l'édition ; aucun e-mail n'est envoyé.
 */
class CodesAccesTest extends TestCase
{
    use RefreshDatabase;

    private User $comite;

    private User $electeur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['auth.mode_connexion' => 'code_imprime']);
        $dia = Structure::where('code', 'DIA')->firstOrFail();
        $this->comite = User::factory()->avecRoles(CodeRole::Comite)->create();
        $agent = Agent::factory()->affecteA($dia, Carbon::parse('2020-01-01'))->create(['matricule' => '104455']);
        $this->electeur = User::factory()->pourAgent($agent)->avecRoles(CodeRole::Electeur)->create(['login' => '104455']);
    }

    /**
     * R42 (exception) : connexion par matricule et code du coupon, sans e-mail ; un code faux ou un compte désactivé
     * est refusé, et l'échec est journalisé.
     */
    public function test_se_connecte_avec_le_matricule_et_le_code_du_coupon(): void
    {
        app(GenererCodesAcces::class)->handle();
        $code = $this->electeur->fresh()->code_acces;

        $this->get(route('login'))->assertOk()->assertSee('Code personnel')->assertDontSee('Recevoir mon code');

        Livewire::test(Connexion::class)->set('login', '104455')->set('code', 'AAAA-BBBB')->call('seConnecter')
            ->assertHasErrors(['code' => 'Matricule ou code incorrect. Vérifiez votre coupon, ou adressez-vous au comité.']);
        $this->assertGuest();
        $this->assertSame(1, JournalAudit::where('action', ActionAuditee::ConnexionEchouee->value)->count());

        Livewire::test(Connexion::class)->set('login', '104455')->set('code', strtolower(GenererCodesAcces::formater($code)))->call('seConnecter')
            ->assertHasNoErrors()->assertRedirect();
        $this->assertAuthenticatedAs($this->electeur);

        auth()->logout();
        $this->electeur->update(['actif' => false]);
        Livewire::test(Connexion::class)->set('login', '104455')->set('code', $code)->call('seConnecter')->assertHasErrors('code');
        $this->assertGuest();
    }

    /**
     * Le composant garde une seule racine dans les deux modes : un commentaire de Livewire hors de la racine fait
     * perdre son état au composant à la première requête (« Snapshot missing »).
     */
    public function test_le_composant_de_connexion_garde_une_seule_racine_dans_les_deux_modes(): void
    {
        foreach (['code_imprime', 'email'] as $mode) {
            config(['auth.mode_connexion' => $mode]);
            $html = trim(Livewire::test(Connexion::class)->html());

            $this->assertStringStartsWith('<div', $html, "Mode {$mode}");
            $this->assertStringEndsWith('</div>', $html, "Mode {$mode}");
        }
    }

    public function test_en_mode_email_la_connexion_par_coupon_est_fermee(): void
    {
        config(['auth.mode_connexion' => 'email']);

        $this->get(route('login'))->assertOk()->assertSee('Recevoir mon code')->assertDontSee('Code personnel');
        Livewire::test(Connexion::class)->set('login', '104455')->set('code', 'AAAABBBB')->call('seConnecter')->assertStatus(404);
    }

    /**
     * R56 : les codes manquants se génèrent sans toucher aux codes existants ; un code régénéré remplace l'ancien.
     * Le journal trace les opérations, jamais les codes.
     */
    public function test_genere_les_codes_manquants_puis_regenere_un_code_perdu(): void
    {
        $this->actingAs($this->comite);
        Livewire::test(CodesAcces::class)->call('genererManquants')->assertDispatched('notifier');
        $ancien = $this->electeur->fresh()->code_acces;
        $this->assertMatchesRegularExpression('/^[A-HJKMNP-Z2-9]{8}$/', $ancien);

        Livewire::test(CodesAcces::class)->call('genererManquants');
        $this->assertSame($ancien, $this->electeur->fresh()->code_acces, 'Un code existant ne change pas.');

        Livewire::test(CodesAcces::class)->call('regenerer', $this->electeur->id)->assertDispatched('notifier');
        $this->assertNotSame($ancien, $this->electeur->fresh()->code_acces);
        Livewire::test(Connexion::class)->set('login', '104455')->set('code', $ancien)->call('seConnecter')->assertHasErrors('code');

        $journal = JournalAudit::whereIn('action', [ActionAuditee::CodesAccesGeneres->value, ActionAuditee::CodeAccesRegenere->value])->orderBy('id')->get();
        $this->assertSame([ActionAuditee::CodesAccesGeneres, ActionAuditee::CodeAccesRegenere], $journal->pluck('action')->all());
        $this->assertStringNotContainsString($ancien, $journal->toJson());
    }

    /**
     * Coupons : les électeurs par structure, à part les comptes qui ont un autre rôle ; réservés au comité et à
     * l'administrateur, jamais au point focal. Chaque impression est tracée.
     */
    public function test_imprime_les_coupons_par_lot_et_les_reserve_au_comite(): void
    {
        app(GenererCodesAcces::class)->handle();

        $electeurs = app(ImprimerCodesAcces::class)->handle(ImprimerCodesAcces::ELECTEURS);
        $autres = app(ImprimerCodesAcces::class)->handle(ImprimerCodesAcces::AUTRES);
        $this->assertSame(1, $electeurs['nombre'], 'Seul l’électeur.');
        $this->assertSame(1, $autres['nombre'], 'Le membre du comité, à part.');
        $this->assertStringStartsWith('%PDF', $electeurs['pdf']);
        $pages = fn (string $pdf): int => preg_match_all('#/Type\s*/Page[^s]#', $pdf);
        $this->assertSame(1, $pages(app(ImprimerCodesAcces::class)->coupon($this->electeur->fresh())['pdf']), 'Un coupon seul tient sur une page.');
        $this->assertSame(1, $pages($electeurs['pdf']), 'Pas de page vide avant la première structure.');

        $this->actingAs($this->comite)->get(route('codes-acces.index'))->assertOk()->assertSee('Coupons des électeurs');
        $this->get(route('codes-acces.lot', 'electeurs'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('codes-acces.coupon', $this->electeur))->assertOk();
        $this->assertSame(5, JournalAudit::where('action', ActionAuditee::CodesAccesImprimes->value)->count());

        $pointFocal = User::factory()->pointFocalDe(Structure::where('code', 'DIA')->firstOrFail())->create();
        $this->actingAs($pointFocal)->get(route('codes-acces.index'))->assertForbidden();
        $this->get(route('codes-acces.lot', 'electeurs'))->assertForbidden();
    }

    /**
     * Filtre par structure d'affectation, sélection par cases à cocher : seuls les comptes cochés reçoivent un code
     * et sortent sur les coupons.
     */
    public function test_genere_et_imprime_les_codes_des_comptes_selectionnes_par_structure(): void
    {
        $dr = Structure::where('code', 'DR')->firstOrFail();
        $autreAgent = Agent::factory()->affecteA($dr, Carbon::parse('2020-01-01'))->create(['matricule' => '107777']);
        $autre = User::factory()->pourAgent($autreAgent)->avecRoles(CodeRole::Electeur)->create(['login' => '107777']);
        $this->actingAs($this->comite);

        $composant = Livewire::test(CodesAcces::class)->set('structure', (string) $dr->id);
        $this->assertSame([$autre->id], $composant->get('comptes')->pluck('id')->all(), 'Seuls les comptes de la DR.');

        $composant->call('selectionnerTout')->assertSet('selection', [(string) $autre->id])
            ->call('genererSelection')->assertDispatched('notifier');
        $this->assertNotNull($autre->fresh()->code_acces);
        $this->assertNull($this->electeur->fresh()->code_acces, 'Le compte non coché n’a pas de code.');

        $composant->call('imprimerSelection')->assertFileDownloaded('codes-acces-selection.pdf');
        $this->assertSame(['lot' => 'sélection', 'nombre' => 1], JournalAudit::where('action', ActionAuditee::CodesAccesImprimes->value)->sole()->details);

        $composant->call('viderSelection')->assertSet('selection', [])
            ->set('structure', '')->call('basculerPage');
        $this->assertCount(3, $composant->get('selection'), 'La page entière : comité, électeur, agent de la DR.');
    }

    public function test_en_mode_code_imprime_aucun_e_mail_ne_part(): void
    {
        config(['mail.default' => 'array']);
        $envoyes = fn (): int => Mail::mailer('array')->getSymfonyTransport()->messages()->count();
        $this->electeur->update(['email' => 'electeur@example.test']);

        app(DemanderCodeConnexion::class)->handle('104455');
        $this->assertSame(0, $envoyes());

        config(['auth.mode_connexion' => 'email']);
        app(DemanderCodeConnexion::class)->handle('104455');
        $this->assertSame(1, $envoyes(), 'En mode e-mail, le code part.');
    }
}
