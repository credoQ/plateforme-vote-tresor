<?php

namespace Tests\Feature\Http\Agents;

use App\Enums\Acces\CodeRole;
use App\Enums\Audit\ActionAuditee;
use App\Livewire\Agents\ImportAgents;
use App\Models\Agent;
use App\Models\JournalAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Import des agents depuis l'écran (administrateur), traité en file d'attente : agents, comptes électeurs (R3) et
 * affectations (R12) créés ensemble ; lignes incomplètes rejetées avec leur motif (R11 à R13).
 */
class ImportAgentsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');
        $this->admin = User::factory()->avecRoles(CodeRole::Administrateur)->create();
    }

    public function test_importe_un_fichier_depuis_l_ecran_et_affiche_le_bilan(): void
    {
        $fichier = UploadedFile::fake()->createWithContent('agents.csv', "Matricule;Nom;Prénom;Sexe;Structure\n100010;HOUNKPE;Bénédicte;F;DIA\n100011;AGBO;Paul;M;INCONNUE\n");

        $this->actingAs($this->admin)->get(route('agents.importer'))->assertOk()->assertSee('Fichier des agents');

        Livewire::test(ImportAgents::class)->set('fichier', $fichier)->call('importer')
            ->assertHasNoErrors()->assertDispatched('notifier')
            ->assertSee('Agents créés')->assertSee('Structure « INCONNUE » inconnue du référentiel.');

        $agent = Agent::where('matricule', '100010')->sole();
        $this->assertSame('DIA', $agent->affectationLe()->structure->code);
        $this->assertSame('100010', $agent->compte->login);
        $this->assertEquals(['fichier' => 'agents.csv', 'crees' => 1, 'mis_a_jour' => 0, 'affectations' => 1, 'rejets' => 1], JournalAudit::where('action', ActionAuditee::ImportAgents->value)->sole()->details);
        $this->assertSame([], Storage::disk('local')->allFiles('imports'), 'Le fichier déposé est supprimé après l’import.');

        Livewire::test(ImportAgents::class)->call('fermerBilan')->assertDontSee('Agents créés');
    }

    public function test_signale_un_fichier_sans_colonne_obligatoire(): void
    {
        $this->actingAs($this->admin);
        $fichier = UploadedFile::fake()->createWithContent('agents.csv', "Matricule;Nom\n100010;HOUNKPE\n");

        Livewire::test(ImportAgents::class)->set('fichier', $fichier)->call('importer')
            ->assertSee('Import impossible.')->assertSee('Colonnes obligatoires absentes : prenoms, sexe, structure.');
        $this->assertSame(0, Agent::count());
    }

    public function test_refuse_un_fichier_d_un_autre_format_et_reserve_l_import_a_l_administrateur(): void
    {
        $this->actingAs($this->admin);
        Livewire::test(ImportAgents::class)->set('fichier', UploadedFile::fake()->create('agents.pdf', 10))->call('importer')
            ->assertHasErrors(['fichier' => 'Le fichier doit être un classeur Excel (.xlsx) ou un fichier CSV.']);

        $this->actingAs(User::factory()->avecRoles(CodeRole::Comite)->create())->get(route('agents.importer'))->assertForbidden();
    }
}
