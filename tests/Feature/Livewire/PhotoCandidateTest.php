<?php

namespace Tests\Feature\Livewire;

use App\Actions\Scrutins\ChangerPhotoCandidate;
use App\Actions\Scrutins\GenererScrutins;
use App\Enums\Acces\CodeRole;
use App\Enums\Audit\ActionAuditee;
use App\Enums\Scrutins\StatutScrutin;
use App\Livewire\Scrutins\PhotoCandidate;
use App\Models\Agent;
use App\Models\Candidate;
use App\Models\Edition;
use App\Models\JournalAudit;
use App\Models\Phase;
use App\Models\Scrutin;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Photo d'une candidate sur le bulletin : ajoutée, remplacée ou retirée par les mêmes personnes que la
 * désactivation (R32, R4), jusqu'à l'ouverture du scrutin (R33), et tracée au journal (R56).
 */
class PhotoCandidateTest extends TestCase
{
    use RefreshDatabase;

    private Scrutin $scrutin;

    private Candidate $candidate;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $edition = Edition::factory()->avecPhases()->enCours()->create([
            'annee' => 2026, 'date_debut' => '2026-11-01', 'date_fin' => '2026-12-31', 'date_gel' => '2026-10-01',
        ]);
        $femme = Agent::factory()->femme()->affecteA(Structure::where('code', 'DIA')->firstOrFail(), Carbon::parse('2020-01-01'))->create();

        app(GenererScrutins::class)->handle($edition->phase(Phase::PRIMAIRES));
        $this->scrutin = Scrutin::where('code', 'STR-DIA')->firstOrFail();
        $this->candidate = $this->scrutin->candidates()->where('agent_id', $femme->id)->firstOrFail();
    }

    /**
     * R56 : le comité ajoute la photo puis la remplace ; l'ancienne est supprimée et chaque changement est tracé.
     */
    public function test_r56_le_comite_ajoute_puis_remplace_la_photo(): void
    {
        $this->actingAs(User::factory()->avecRoles(CodeRole::Comite)->create());

        $this->envoyer(UploadedFile::fake()->image('photo.jpg', 600, 690))
            ->assertHasNoErrors()->assertDispatched('photo-candidate-modifiee')->assertSet('candidateId', null);
        $premiere = $this->candidate->fresh()->photo;
        Storage::disk('local')->assertExists($premiere);
        $this->assertStringStartsWith(ChangerPhotoCandidate::DOSSIER.'/', $premiere);

        $this->envoyer(UploadedFile::fake()->image('autre.jpg', 600, 690))->assertHasNoErrors();
        Storage::disk('local')->assertMissing($premiere);
        Storage::disk('local')->assertExists($this->candidate->fresh()->photo);

        $this->assertSame(['ajout', 'remplacement'], JournalAudit::where('action', ActionAuditee::PhotoCandidateModifiee->value)
            ->orderBy('id')->get()->pluck('details.operation')->all());
    }

    /**
     * R32 : la candidate gère sa propre photo, depuis son espace ; elle la retire, le fichier est supprimé.
     */
    public function test_r32_la_candidate_ajoute_puis_retire_sa_photo(): void
    {
        $this->actingAs(User::factory()->pourAgent($this->candidate->agent)->avecRoles(CodeRole::Electeur)->create());

        $this->envoyer(UploadedFile::fake()->image('moi.png', 600, 690))->assertHasNoErrors();
        $chemin = $this->candidate->fresh()->photo;

        Livewire::test(PhotoCandidate::class)->call('ouvrir', $this->candidate->id)->call('retirer')
            ->assertHasNoErrors()->assertDispatched('photo-candidate-modifiee');
        $this->assertNull($this->candidate->fresh()->photo);
        Storage::disk('local')->assertMissing($chemin);
    }

    /**
     * R4 : un point focal d'une autre structure, ou un simple électeur, ne peut pas changer la photo.
     */
    public function test_r4_refuse_la_fenetre_a_qui_ne_peut_pas_desactiver_la_candidate(): void
    {
        $this->actingAs(User::factory()->pointFocalDe(Structure::where('code', 'DR')->firstOrFail())->create());
        Livewire::test(PhotoCandidate::class)->call('ouvrir', $this->candidate->id)->assertForbidden();

        $this->actingAs(User::factory()->avecRoles(CodeRole::Electeur)->create());
        Livewire::test(PhotoCandidate::class)->call('ouvrir', $this->candidate->id)->assertForbidden();
    }

    /**
     * R33 : une fois le scrutin ouvert, la photo ne change plus ; le refus s'affiche en fenêtre.
     */
    public function test_r33_refuse_de_changer_la_photo_apres_l_ouverture(): void
    {
        $this->actingAs(User::factory()->avecRoles(CodeRole::Comite)->create());
        $composant = Livewire::test(PhotoCandidate::class)->call('ouvrir', $this->candidate->id);
        $this->scrutin->update(['statut' => StatutScrutin::Ouvert]);

        $composant->set('photo', UploadedFile::fake()->image('photo.jpg', 600, 690))->call('enregistrer')
            ->assertHasErrors('candidate')->assertDispatched('erreur', message: 'Le scrutin est ouvert : la photo ne peut plus être modifiée.');
        $this->assertNull($this->candidate->fresh()->photo);
        $this->assertSame([], Storage::disk('local')->allFiles(ChangerPhotoCandidate::DOSSIER), 'Rien n’est enregistré.');
    }

    public function test_refuse_un_fichier_qui_n_est_pas_une_photo(): void
    {
        $this->actingAs(User::factory()->avecRoles(CodeRole::Comite)->create());

        $this->envoyer(UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'))->assertHasErrors('photo');
        $this->envoyer(UploadedFile::fake()->image('petite.jpg', 120, 120))->assertHasErrors(['photo' => 'La photo est trop petite (200 pixels de côté au moins).']);
        $this->assertNull($this->candidate->fresh()->photo);
    }

    private function envoyer(UploadedFile $photo): Testable
    {
        return Livewire::test(PhotoCandidate::class)->call('ouvrir', $this->candidate->id)->set('photo', $photo)->call('enregistrer');
    }
}
