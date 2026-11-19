<?php

namespace Tests\Feature\Actions\Referentiel;

use App\Actions\Referentiel\EnregistrerStructure;
use App\Enums\Referentiel\CodeTypeStructure;
use App\Models\Departement;
use App\Models\Structure;
use App\Models\TypeStructure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EnregistrerStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_cree_une_direction_centrale_sans_departement(): void
    {
        $structure = $this->enregistrer([
            'code' => 'DTEST',
            'libelle' => 'Direction de test',
            'type_structure_id' => $this->type(CodeTypeStructure::DirectionCentrale),
        ]);

        $this->assertDatabaseHas('structures', ['id' => $structure->id, 'code' => 'DTEST', 'niveau' => 1, 'actif' => true]);
    }

    /**
     * R9 : une TD ou une Trésorerie communale est située dans un département.
     */
    public function test_r9_rejette_une_tresorerie_departementale_sans_departement(): void
    {
        $this->attendreErreur('departement_id', fn () => $this->enregistrer([
            'code' => 'TD-TEST',
            'libelle' => 'Trésorerie départementale de test',
            'type_structure_id' => $this->type(CodeTypeStructure::TresorerieDepartementale),
        ]));
    }

    /**
     * R8 : une Trésorerie communale est rattachée à la TD de son département.
     */
    public function test_r8_cree_une_tresorerie_communale_rattachee_a_la_td_de_son_departement(): void
    {
        $td = Structure::where('code', 'TD-ATL')->firstOrFail();

        $tc = $this->enregistrer([
            'code' => 'TC-TEST',
            'libelle' => 'Trésorerie communale de test',
            'type_structure_id' => $this->type(CodeTypeStructure::TresorerieCommunale),
            'departement_id' => $td->departement_id,
            'structure_parente_id' => $td->id,
        ]);

        $this->assertTrue($tc->structureParente->is($td));
    }

    public function test_r8_rejette_une_tresorerie_communale_sans_td_de_rattachement(): void
    {
        $this->attendreErreur('structure_parente_id', fn () => $this->enregistrer([
            'code' => 'TC-SANS-TD',
            'libelle' => 'Trésorerie communale orpheline',
            'type_structure_id' => $this->type(CodeTypeStructure::TresorerieCommunale),
            'departement_id' => Departement::where('code', 'ATL')->value('id'),
            'structure_parente_id' => Structure::where('code', 'DIA')->value('id'),
        ]));
    }

    public function test_r8_rejette_une_tresorerie_communale_d_un_autre_departement_que_sa_td(): void
    {
        $this->attendreErreur('departement_id', fn () => $this->enregistrer([
            'code' => 'TC-HORS-DEP',
            'libelle' => 'Trésorerie communale mal placée',
            'type_structure_id' => $this->type(CodeTypeStructure::TresorerieCommunale),
            'departement_id' => Departement::where('code', 'ZOU')->value('id'),
            'structure_parente_id' => Structure::where('code', 'TD-ATL')->value('id'),
        ]));
    }

    /**
     * R8 : un service est rattaché à une structure électorale de niveau 1.
     */
    public function test_r8_rejette_un_service_sans_structure_electorale_parente(): void
    {
        $this->attendreErreur('structure_parente_id', fn () => $this->enregistrer([
            'code' => 'SERV-ORPHELIN',
            'libelle' => 'Service orphelin',
            'niveau' => Structure::NIVEAU_SERVICE,
            'type_structure_id' => $this->type(CodeTypeStructure::DirectionCentrale),
        ]));
    }

    public function test_r8_rejette_un_service_rattache_a_un_autre_service(): void
    {
        $service = Structure::factory()->serviceDe(Structure::where('code', 'DIA')->firstOrFail())->create();

        $this->attendreErreur('structure_parente_id', fn () => $this->enregistrer([
            'code' => 'SOUS-SERVICE',
            'libelle' => 'Sous-service',
            'niveau' => Structure::NIVEAU_SERVICE,
            'type_structure_id' => $service->type_structure_id,
            'structure_parente_id' => $service->id,
        ]));
    }

    public function test_r8_rejette_une_structure_rattachee_a_elle_meme(): void
    {
        $dia = Structure::where('code', 'DIA')->firstOrFail();

        $this->attendreErreur('structure_parente_id', fn () => $this->enregistrer([
            'code' => $dia->code,
            'libelle' => $dia->libelle,
            'type_structure_id' => $dia->type_structure_id,
            'structure_parente_id' => $dia->id,
        ], $dia));
    }

    public function test_rejette_un_code_deja_utilise_par_une_autre_structure(): void
    {
        $this->attendreErreur('code', fn () => $this->enregistrer([
            'code' => 'DIA',
            'libelle' => 'Doublon',
            'type_structure_id' => $this->type(CodeTypeStructure::DirectionCentrale),
        ]));
    }

    public function test_met_a_jour_une_structure_en_conservant_son_code(): void
    {
        $dmg = Structure::where('code', 'DMG')->firstOrFail();

        $this->enregistrer([
            'code' => 'DMG',
            'libelle' => 'Direction des Moyens généraux et du Patrimoine',
            'type_structure_id' => $dmg->type_structure_id,
        ], $dmg);

        $this->assertSame('Direction des Moyens généraux et du Patrimoine', $dmg->fresh()->libelle);
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    private function enregistrer(array $donnees, ?Structure $structure = null): Structure
    {
        return app(EnregistrerStructure::class)->handle($donnees, $structure);
    }

    private function type(CodeTypeStructure $type): int
    {
        return TypeStructure::where('code', $type->value)->value('id');
    }

    private function attendreErreur(string $champ, callable $action): void
    {
        try {
            $action();
            $this->fail("Une erreur de validation était attendue sur « {$champ} ».");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($champ, $exception->errors());
        }
    }
}
