<?php

namespace Tests\Feature\Http\Comite;

use App\Enums\Acces\CodeRole;
use App\Models\Edition;
use App\Models\FemmeModele;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_r59_le_comite_importe_et_consulte_l_historique(): void
    {
        Edition::factory()->create(['annee' => 2026, 'delai_reeligibilite' => 3]);
        $comite = User::factory()->avecRoles(CodeRole::Comite)->create();

        $this->actingAs($comite)->get(route('femmes-modeles.index'))->assertOk()
            ->assertSee('Aucune femme modèle enregistrée')->assertSee('délai de rééligibilité de 3 an(s)');

        $fichier = UploadedFile::fake()->createWithContent('historique.csv', "matricule;annee;nom\n104876;2024;AHOUANSOU Clarisse\n104877;2021;KPADE Inès\n;2022;Sans matricule\n");
        $this->post(route('femmes-modeles.importer'), ['fichier' => $fichier])
            ->assertSessionHas('succes', '2 femme(s) modèle(s) enregistrée(s).')->assertSessionHas('rejets', [4 => 'Matricule absent.']);

        $this->get(route('femmes-modeles.index'))->assertSee('AHOUANSOU Clarisse')->assertSee('Inéligible jusqu’en 2027')->assertSee('KPADE Inès');

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
        $this->post(route('femmes-modeles.importer'))->assertForbidden();
    }

    public function test_un_fichier_sans_les_colonnes_attendues_est_refuse(): void
    {
        $this->actingAs(User::factory()->avecRoles(CodeRole::Comite)->create());

        $fichier = UploadedFile::fake()->createWithContent('historique.csv', "nom;prenoms\nAHOUANSOU;Clarisse\n");
        $this->post(route('femmes-modeles.importer'), ['fichier' => $fichier])->assertSessionHasErrors('fichier');
    }
}
