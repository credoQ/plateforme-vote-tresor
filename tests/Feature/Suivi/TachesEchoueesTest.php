<?php

namespace Tests\Feature\Suivi;

use App\Enums\Acces\CodeRole;
use App\Enums\Audit\ActionAuditee;
use App\Jobs\Resultats\GenererFichierProcesVerbal;
use App\Models\JournalAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TachesEchoueesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = User::factory()->avecRoles(CodeRole::Administrateur)->create();
    }

    public function test_liste_les_taches_en_echec_pour_l_administrateur_seulement(): void
    {
        $this->tacheEnEchec();

        $this->actingAs(User::factory()->avecRoles(CodeRole::Comite)->create())->get(route('taches-echouees.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('taches-echouees.index'))
            ->assertOk()
            ->assertSee('GenererFichierProcesVerbal')
            ->assertSee('Connexion au serveur de messagerie refusée')
            ->assertSee('Relancer toutes les tâches');
    }

    /**
     * Une tâche relancée repasse en file d'attente ; la relance est journalisée (R56).
     */
    public function test_relance_une_tache_en_echec(): void
    {
        $uuid = $this->tacheEnEchec();

        $this->actingAs($this->admin)->post(route('taches-echouees.relancer', $uuid))->assertRedirect(route('taches-echouees.index'));

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertSame('GenererFichierProcesVerbal', JournalAudit::where('action', ActionAuditee::TacheRelancee->value)->sole()->details['tache']);
    }

    public function test_relance_toutes_les_taches_et_supprime_une_tache(): void
    {
        $this->tacheEnEchec();
        $this->tacheEnEchec();
        $aSupprimer = $this->tacheEnEchec();

        $this->actingAs($this->admin)->post(route('taches-echouees.supprimer', $aSupprimer))->assertSessionHas('succes');
        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $aSupprimer]);
        $this->assertSame(0, DB::table('jobs')->count());

        $this->post(route('taches-echouees.relancer-tout'))->assertSessionHas('succes');
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(2, DB::table('jobs')->count());
        $this->assertSame(['TACHE_SUPPRIMEE', 'TACHE_RELANCEE'], JournalAudit::orderBy('id')->pluck('action')->map->value->all());

        $this->post(route('taches-echouees.relancer', (string) Str::uuid()))->assertNotFound();
    }

    private function tacheEnEchec(): string
    {
        $uuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode([
                'uuid' => $uuid,
                'displayName' => GenererFichierProcesVerbal::class,
                'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'attempts' => 3,
                'data' => ['commandName' => GenererFichierProcesVerbal::class, 'command' => serialize(new GenererFichierProcesVerbal(1))],
            ]),
            'exception' => "RuntimeException: Connexion au serveur de messagerie refusée\n#0 trace",
            'failed_at' => now(),
        ]);

        return $uuid;
    }
}
