<?php

namespace Tests\Feature\Database;

use App\Enums\Referentiel\CodePoleElectoral;
use App\Enums\Referentiel\CodeTypeStructure;
use App\Models\Departement;
use App\Models\Structure;
use Database\Seeders\StructuresDeconcentreesSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StructuresDeconcentreesSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * R8, R9 : une TD par département et une Trésorerie communale par commune, rattachée à la TD de son département.
     */
    public function test_r9_cree_une_td_par_departement_et_une_tc_par_commune_rattachee_a_sa_td(): void
    {
        $this->seed(StructuresDeconcentreesSeeder::class);

        $this->assertSame(77, $this->duType(CodeTypeStructure::TresorerieCommunale)->count());
        Departement::each(function (Departement $departement): void {
            $td = Structure::where('code', 'TD-'.$departement->code)->firstOrFail();
            $this->assertSame($departement->id, $td->departement_id);
            $this->assertSame(
                count(StructuresDeconcentreesSeeder::COMMUNES[$departement->code]),
                $td->structuresFilles()->where('departement_id', $departement->id)->count(),
            );
        });
    }

    public function test_forme_le_code_et_le_libelle_de_la_tresorerie_communale(): void
    {
        $this->seed(StructuresDeconcentreesSeeder::class);

        $this->assertSame([
            'TC-ABOMEY-CALAVI' => "Trésorerie communale d'Abomey-Calavi",
            'TC-AGUEGUES' => 'Trésorerie communale des Aguégués',
            'TC-NDALI' => "Trésorerie communale de N'Dali",
            'TC-OUIDAH' => 'Trésorerie communale de Ouidah',
        ], Structure::whereIn('code', ['TC-ABOMEY-CALAVI', 'TC-AGUEGUES', 'TC-NDALI', 'TC-OUIDAH'])->orderBy('code')->pluck('libelle', 'code')->all());
    }

    /**
     * R7, R10 : les TMIE et l'ACD sont des structures électorales autonomes du pôle structures, sans département.
     */
    public function test_r10_cree_les_tmie_et_l_acd_dans_le_pole_structures(): void
    {
        $this->seed(StructuresDeconcentreesSeeder::class);

        $structures = $this->duType(CodeTypeStructure::Tmie)->get()->merge($this->duType(CodeTypeStructure::Acd)->get());

        $this->assertCount(count(StructuresDeconcentreesSeeder::TMIE) + 1, $structures);
        $this->assertTrue($structures->every(fn (Structure $s) => $s->departement_id === null && $s->structure_parente_id === null));
        $this->assertSame(0, $this->duType(CodeTypeStructure::Tmie)->duPole(CodePoleElectoral::Departemental)->count());
    }

    public function test_une_seconde_execution_ne_cree_aucun_doublon(): void
    {
        $this->seed(StructuresDeconcentreesSeeder::class);
        $avant = Structure::count();

        $this->seed(StructuresDeconcentreesSeeder::class);

        $this->assertSame($avant, Structure::count());
    }

    private function duType(CodeTypeStructure $type): Builder
    {
        return Structure::whereHas('typeStructure', fn ($q) => $q->where('code', $type->value));
    }
}
