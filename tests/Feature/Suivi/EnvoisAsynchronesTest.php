<?php

namespace Tests\Feature\Suivi;

use App\Actions\Acces\DemanderCodeConnexion;
use App\Jobs\Suivi\EnvoyerAnnonceScrutin;
use App\Models\Agent;
use App\Models\Inscription;
use App\Models\Scrutin;
use App\Models\Structure;
use App\Models\User;
use App\Notifications\AnnonceScrutin;
use App\Notifications\CodeDeConnexion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Tout envoi d'e-mail passe par la file d'attente.
 */
class EnvoisAsynchronesTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_code_de_connexion_part_sur_la_file_prioritaire_chiffre(): void
    {
        Queue::fake();
        User::factory()->create(['login' => '102345', 'email' => 'clarisse@example.test']);

        app(DemanderCodeConnexion::class)->handle('102345');

        Queue::assertPushedOn(CodeDeConnexion::FILE, SendQueuedNotifications::class, function (SendQueuedNotifications $job): bool {
            return $job->notification instanceof CodeDeConnexion
                && $job->shouldBeEncrypted
                && $job->retryUntil()->isFuture()
                && $job->retryUntil()->lte(now()->addMinutes(DemanderCodeConnexion::VALIDITE_MINUTES));
        });
    }

    public function test_le_code_n_apparait_pas_en_clair_dans_la_table_des_jobs(): void
    {
        config(['queue.default' => 'database']);
        User::factory()->create(['login' => '102345', 'email' => 'clarisse@example.test']);

        app(DemanderCodeConnexion::class)->handle('102345');

        $job = DB::table('jobs')->sole();
        $this->assertSame(CodeDeConnexion::FILE, $job->queue);
        $this->assertStringNotContainsString('clarisse@example.test', $job->payload);
        $this->assertStringNotContainsString('Votre code', $job->payload);
    }

    public function test_les_annonces_partent_une_par_une_sur_la_file_des_mails(): void
    {
        $scrutin = Scrutin::factory()->create();
        foreach (['a@example.test', 'b@example.test', null] as $email) {
            Inscription::factory()->create(['scrutin_id' => $scrutin->id, 'agent_id' => Agent::factory()->create(['email' => $email])->id, 'structure_id' => Structure::where('code', 'DIA')->value('id')]);
        }

        Queue::fake();
        EnvoyerAnnonceScrutin::dispatch($scrutin->id, AnnonceScrutin::OUVERTURE);
        Queue::assertPushedOn(AnnonceScrutin::FILE, EnvoyerAnnonceScrutin::class);

        (new EnvoyerAnnonceScrutin($scrutin->id, AnnonceScrutin::OUVERTURE))->handle();

        Queue::assertPushedOn(AnnonceScrutin::FILE, SendQueuedNotifications::class);
        Queue::assertPushed(SendQueuedNotifications::class, 2);
    }
}
