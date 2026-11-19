<?php

namespace Tests\Feature\Livewire;

use App\Actions\Audit\Journaliser;
use App\Enums\Acces\CodeRole;
use App\Enums\Audit\ActionAuditee;
use App\Enums\Editions\StatutEdition;
use App\Livewire\Acces\ListeUtilisateurs;
use App\Livewire\Editions\ListeEditions;
use App\Livewire\Referentiel\ListeStructures;
use App\Livewire\Suivi\JournalAudit;
use App\Livewire\Suivi\TableauDeBord;
use App\Models\Edition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Listes de la console en Livewire : filtres appliqués sans rechargement, et droits contrôlés à chaque requête
 * du composant (mises à jour comprises), pas seulement au premier affichage de la page.
 */
class ListesTest extends TestCase
{
    use RefreshDatabase;

    public function test_filtre_les_structures_et_revient_a_la_premiere_page(): void
    {
        Livewire::actingAs(User::factory()->avecRoles(CodeRole::Administrateur)->create())
            ->withQueryParams(['page' => 3])
            ->test(ListeStructures::class)
            ->set('recherche', 'td-zou')
            ->assertSet('paginators.page', 1)
            ->assertSee('TD-ZOU')->assertDontSee('TD-ATL')
            ->assertSee('Réinitialiser');
    }

    public function test_filtre_les_utilisateurs_par_role_pour_l_administrateur_seulement(): void
    {
        User::factory()->avecRoles(CodeRole::Comite)->create(['name' => 'Membre du comité']);
        User::factory()->avecRoles(CodeRole::Observateur)->create(['name' => 'Observatrice']);

        Livewire::actingAs(User::factory()->avecRoles(CodeRole::Administrateur)->create())
            ->test(ListeUtilisateurs::class)
            ->set('role', CodeRole::Observateur->value)
            ->assertSee('Observatrice')->assertDontSee('Membre du comité');

        Livewire::actingAs(User::factory()->avecRoles(CodeRole::Comite)->create())
            ->test(ListeUtilisateurs::class)
            ->assertForbidden();
    }

    /**
     * R56 : le journal se filtre par opération ; un électeur ne peut pas le consulter.
     */
    public function test_r56_filtre_le_journal_par_operation(): void
    {
        $observateur = User::factory()->avecRoles(CodeRole::Observateur)->create(['login' => 'obs.journal']);
        app(Journaliser::class)->handle(ActionAuditee::ConnexionEchouee, null, ['motif' => 'compte inconnu'], null, 'matricule-inconnu');
        app(Journaliser::class)->handle(ActionAuditee::CodeConnexionEnvoye, $observateur, [], $observateur);

        Livewire::actingAs($observateur)
            ->test(JournalAudit::class)
            ->set('action', ActionAuditee::ConnexionEchouee->value)
            ->assertSee('matricule-inconnu')->assertDontSee('obs.journal');

        Livewire::actingAs(User::factory()->avecRoles(CodeRole::Electeur)->create())
            ->test(JournalAudit::class)
            ->assertForbidden();
    }

    public function test_filtre_les_editions_par_statut_pour_le_comite_seulement(): void
    {
        Edition::factory()->create(['annee' => 2031, 'libelle' => 'Édition préparée']);
        Edition::factory()->enCours()->create(['annee' => 2032, 'libelle' => 'Édition lancée']);

        Livewire::actingAs(User::factory()->avecRoles(CodeRole::Comite)->create())
            ->test(ListeEditions::class)
            ->set('statut', StatutEdition::EnCours->value)
            ->assertSee('Édition lancée')->assertDontSee('Édition préparée');

        Livewire::actingAs(User::factory()->avecRoles(CodeRole::Administrateur)->create())
            ->test(ListeEditions::class)
            ->assertForbidden();
    }

    /**
     * R46 : le tableau de bord se rafraîchit seul et change de phase sans rechargement ; un électeur n'y a pas accès.
     */
    public function test_r46_le_tableau_de_bord_se_rafraichit_et_change_de_phase(): void
    {
        Edition::factory()->avecPhases()->enCours()->create(['annee' => 2026]);

        Livewire::actingAs(User::factory()->avecRoles(CodeRole::Comite)->create())
            ->test(TableauDeBord::class)
            ->assertSeeHtml('wire:poll.60s')
            ->call('choisirPhase', 2)
            ->assertSet('phase', '2')
            ->assertSee('actualisation automatique');

        Livewire::actingAs(User::factory()->avecRoles(CodeRole::Electeur)->create())
            ->test(TableauDeBord::class)
            ->assertForbidden();
    }
}
