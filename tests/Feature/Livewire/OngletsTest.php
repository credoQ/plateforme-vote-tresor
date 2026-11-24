<?php

namespace Tests\Feature\Livewire;

use App\Actions\Scrutins\GenererScrutins;
use App\Enums\Acces\CodeRole;
use App\Livewire\Editions\ScrutinsDeLaPhase;
use App\Models\Agent;
use App\Models\Edition;
use App\Models\Phase;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Zones à onglets, filtres et pagination converties en composants Livewire : seule la zone est mise à jour, et les
 * droits sont contrôlés à chaque requête du composant.
 */
class OngletsTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_onglets_de_la_phase_changent_sans_recharger_la_page(): void
    {
        $edition = Edition::factory()->avecPhases()->enCours()->create(['annee' => 2026, 'date_debut' => '2026-11-01', 'date_fin' => '2026-12-31', 'date_gel' => '2026-10-01']);
        $depuis = Carbon::parse('2020-01-01');
        Agent::factory()->femme()->affecteA(Structure::where('code', 'DIA')->firstOrFail(), $depuis)->create();
        Agent::factory()->femme()->affecteA(Structure::where('code', 'TD-ATL')->firstOrFail(), $depuis)->create();
        $phase = $edition->phase(Phase::PRIMAIRES);
        app(GenererScrutins::class)->handle($phase);

        Livewire::actingAs(User::factory()->avecRoles(CodeRole::Comite)->create())
            ->test(ScrutinsDeLaPhase::class, ['phase' => $phase])
            ->assertSee('Scrutin de structure · Direction de l&#039;Informatique et des Archives', false)->assertSee('Atlantique')
            ->set('recherche', 'atl')
            ->assertSee('Atlantique')->assertDontSee('Informatique')
            ->call('choisirOnglet', 'colleges')
            ->assertSet('onglet', 'colleges')->assertSet('statut', '')
            ->assertSee('Pôle départemental · Un scrutin par département ou par structure')
            ->call('choisirOnglet', 'resultats')
            ->assertSee('Aucun résultat à valider.');
    }

    public function test_les_onglets_de_la_phase_sont_reserves_au_comite(): void
    {
        $phase = Edition::factory()->avecPhases()->create()->phase(Phase::PRIMAIRES);

        Livewire::actingAs(User::factory()->avecRoles(CodeRole::Observateur)->create())
            ->test(ScrutinsDeLaPhase::class, ['phase' => $phase])
            ->assertForbidden();
    }
}
