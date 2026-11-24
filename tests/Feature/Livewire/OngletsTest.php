<?php

namespace Tests\Feature\Livewire;

use App\Actions\Scrutins\GenererScrutins;
use App\Enums\Acces\CodeRole;
use App\Livewire\Editions\ScrutinsDeLaPhase;
use App\Livewire\Scrutins\ContenuDuScrutin;
use App\Models\Agent;
use App\Models\Edition;
use App\Models\Phase;
use App\Models\Scrutin;
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

    /**
     * R4 : sur la fiche d'un scrutin, les onglets changent sur place ; le point focal ne voit que son périmètre.
     */
    public function test_r4_les_onglets_du_scrutin_changent_et_respectent_le_perimetre(): void
    {
        $edition = Edition::factory()->avecPhases()->enCours()->create(['annee' => 2026, 'date_debut' => '2026-11-01', 'date_fin' => '2026-12-31', 'date_gel' => '2026-10-01']);
        $depuis = Carbon::parse('2020-01-01');
        Agent::factory()->femme()->affecteA(Structure::where('code', 'TD-ATL')->firstOrFail(), $depuis)->create(['nom' => 'HOUNSOU']);
        Agent::factory()->femme()->affecteA(Structure::where('code', 'TC-OUIDAH')->firstOrFail(), $depuis)->create(['nom' => 'AKPLOGAN']);
        app(GenererScrutins::class)->handle($edition->phase(Phase::PRIMAIRES));
        $scrutin = Scrutin::where('code', 'DEP-ATL')->firstOrFail();

        Livewire::actingAs(User::factory()->avecRoles(CodeRole::Comite)->create())
            ->test(ContenuDuScrutin::class, ['scrutin' => $scrutin])
            ->assertSet('onglet', 'candidates')
            ->assertSee('HOUNSOU')->assertSee('AKPLOGAN')
            ->set('rechercheCandidate', 'akplogan')
            ->assertSee('AKPLOGAN')->assertDontSee('HOUNSOU')
            ->call('choisirOnglet', 'zone')
            ->assertSee('TC-OUIDAH')
            ->call('choisirOnglet', 'resultats')
            ->assertSee('Les résultats seront disponibles après le dépouillement.');

        Livewire::actingAs(User::factory()->pointFocalDe(Structure::where('code', 'TC-OUIDAH')->firstOrFail())->create())
            ->test(ContenuDuScrutin::class, ['scrutin' => $scrutin])
            ->assertSee('AKPLOGAN')->assertDontSee('HOUNSOU')
            ->call('choisirOnglet', 'liste')
            ->assertSee('AKPLOGAN')->assertDontSee('HOUNSOU');

        Livewire::actingAs(User::factory()->pourAgent(Agent::factory()->create())->avecRoles(CodeRole::Electeur)->create())
            ->test(ContenuDuScrutin::class, ['scrutin' => $scrutin])
            ->assertForbidden();
    }
}
