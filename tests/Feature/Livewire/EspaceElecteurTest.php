<?php

namespace Tests\Feature\Livewire;

use App\Enums\Acces\CodeRole;
use App\Enums\Scrutins\StatutCandidate;
use App\Livewire\Electeur\Accueil;
use App\Livewire\Resultats\Palmares;
use App\Models\Agent;
use App\Models\Candidate;
use App\Models\Edition;
use App\Models\Inscription;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Espace électeur en Livewire : retrait et réactivation de candidature sans rechargement, palmarès filtré.
 */
class EspaceElecteurTest extends TestCase
{
    use RefreshDatabase;

    /**
     * R32, R34 : la candidate retire puis réactive sa candidature depuis son accueil, sans recharger la page.
     */
    public function test_r32_la_candidate_retire_puis_reactive_sa_candidature(): void
    {
        $candidate = Candidate::factory()->create();
        Inscription::factory()->create(['scrutin_id' => $candidate->scrutin_id, 'agent_id' => $candidate->agent_id, 'structure_id' => Structure::where('code', 'DIA')->value('id')]);
        $elle = User::factory()->pourAgent($candidate->agent)->avecRoles(CodeRole::Electeur)->create();

        Livewire::actingAs($elle)->test(Accueil::class)
            ->call('desister', $candidate->id, 'Raisons personnelles')
            ->assertDispatched('notifier', message: 'Votre candidature est retirée. Vous restez électrice.', ton: 'succes');
        $this->assertSame(StatutCandidate::Desactivee, $candidate->fresh()->statut);
        $this->assertSame('Raisons personnelles', $candidate->fresh()->motif_desistement);

        Livewire::actingAs($elle)->test(Accueil::class)
            ->call('reactiver', $candidate->id)
            ->assertDispatched('notifier', message: 'Votre candidature est réactivée.', ton: 'succes');
        $this->assertSame(StatutCandidate::Active, $candidate->fresh()->statut);
    }

    /**
     * R32 : une autre électrice ne peut pas retirer la candidature de quelqu'un d'autre.
     */
    public function test_r32_une_autre_electrice_ne_peut_pas_retirer_la_candidature(): void
    {
        $candidate = Candidate::factory()->create();
        $autre = User::factory()->pourAgent(Agent::factory()->create())->avecRoles(CodeRole::Electeur)->create();

        Livewire::actingAs($autre)->test(Accueil::class)
            ->call('desister', $candidate->id)
            ->assertForbidden();
        $this->assertSame(StatutCandidate::Active, $candidate->fresh()->statut);
    }

    public function test_le_palmares_change_d_edition_sans_rechargement(): void
    {
        Edition::factory()->avecPhases()->create(['annee' => 2031, 'libelle' => 'Édition 2031']);
        Edition::factory()->avecPhases()->enCours()->create(['annee' => 2032, 'libelle' => 'Édition 2032']);

        Livewire::actingAs(User::factory()->pourAgent(Agent::factory()->create())->avecRoles(CodeRole::Electeur)->create())
            ->test(Palmares::class)
            ->assertSee('Édition 2032')
            ->set('annee', '2031')
            ->assertSee('Édition 2031')->assertSet('phase', '');
    }
}
