<?php

namespace Tests\Feature\Http\Comite;

use App\Enums\Acces\CodeRole;
use App\Enums\Editions\SourceFemmeModele;
use App\Models\Edition;
use App\Models\FemmeModele;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R59 : le comité consulte l'historique des femmes modèles, l'importe et retire une ligne importée par erreur.
 */
class FemmesModelesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_r59_le_comite_ajoute_et_consulte_l_historique(): void
    {
        Edition::factory()->create(['annee' => 2026, 'delai_reeligibilite' => 3]);
        $comite = User::factory()->avecRoles(CodeRole::Comite)->create();

        $this->actingAs($comite)->get(route('femmes-modeles.index'))->assertOk()
            ->assertSee('Aucune femme modèle enregistrée')->assertSee('délai de rééligibilité de 3 an(s)')
            ->assertSee('Ajouter une femme modèle')->assertDontSee('Importer');

        $this->get(route('femmes-modeles.create'))->assertOk()->assertSee('Ajouter à l’historique');
        $this->post(route('femmes-modeles.store'), ['matricule' => '104876', 'annee' => '2024', 'nom' => 'AHOUANSOU Clarisse'])
            ->assertRedirect(route('femmes-modeles.index'))->assertSessionHas('succes', 'AHOUANSOU Clarisse (2024) est ajoutée à l’historique.');
        $this->post(route('femmes-modeles.store'), ['matricule' => '104877', 'annee' => '2021', 'nom' => 'KPADE Inès', 'scrutin' => 'Finale']);
        $this->post(route('femmes-modeles.store'), ['matricule' => '', 'annee' => '1950'])->assertSessionHasErrors(['matricule', 'annee']);

        $this->get(route('femmes-modeles.index'))->assertSee('AHOUANSOU Clarisse')->assertSee('Inéligible jusqu’en 2027')->assertSee('KPADE Inès');
        $this->assertSame(SourceFemmeModele::Saisie, FemmeModele::where('matricule', '104876')->sole()->source);

        $ligne = FemmeModele::where('matricule', '104877')->sole();
        $this->post(route('femmes-modeles.destroy', $ligne))->assertSessionHas('succes');
        $this->assertModelMissing($ligne);
    }

    public function test_r59_filtre_l_historique_par_nom_et_par_annee(): void
    {
        FemmeModele::factory()->create(['nom_complet' => 'AHOUANSOU Clarisse', 'annee' => 2024]);
        FemmeModele::factory()->create(['nom_complet' => 'KPADE Inès', 'annee' => 2021]);
        $comite = User::factory()->avecRoles(CodeRole::Comite)->create();

        $this->actingAs($comite)->get(route('femmes-modeles.index', ['annee' => 2021]))
            ->assertOk()->assertSee('KPADE Inès')->assertDontSee('AHOUANSOU Clarisse');
        $this->get(route('femmes-modeles.index', ['recherche' => 'ahouansou']))
            ->assertSee('AHOUANSOU Clarisse')->assertDontSee('KPADE Inès');
    }

    public function test_r59_l_historique_est_reserve_au_comite(): void
    {
        $pointFocal = User::factory()->pointFocalDe(Structure::where('code', 'DIA')->firstOrFail())->create();

        $this->actingAs($pointFocal)->get(route('femmes-modeles.index'))->assertForbidden();
        $this->get(route('femmes-modeles.create'))->assertForbidden();
        $this->post(route('femmes-modeles.store'), ['matricule' => '104876', 'annee' => '2024'])->assertForbidden();
    }
}
