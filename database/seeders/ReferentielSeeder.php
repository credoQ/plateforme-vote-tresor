<?php

namespace Database\Seeders;

use App\Enums\Referentiel\CodePoleElectoral;
use App\Enums\Referentiel\CodeTypeStructure;
use App\Models\Departement;
use App\Models\PoleElectoral;
use App\Models\TypeStructure;
use Illuminate\Database\Seeder;

/**
 * Données de référence du référentiel : pôles électoraux (R6), types de structure (R7) et départements (R9).
 *
 * Les structures sont chargées par StructuresCentralesSeeder et StructuresDeconcentreesSeeder.
 * Idempotent : les lignes sont repérées par leur code.
 */
class ReferentielSeeder extends Seeder
{
    /**
     * Départements du Bénin : code => libellé.
     *
     * @var array<string, string>
     */
    public const DEPARTEMENTS = [
        'ALI' => 'Alibori',
        'ATA' => 'Atacora',
        'ATL' => 'Atlantique',
        'BOR' => 'Borgou',
        'COL' => 'Collines',
        'COU' => 'Couffo',
        'DON' => 'Donga',
        'LIT' => 'Littoral',
        'MON' => 'Mono',
        'OUE' => 'Ouémé',
        'PLA' => 'Plateau',
        'ZOU' => 'Zou',
    ];

    public function run(): void
    {
        $poles = [];
        foreach (CodePoleElectoral::cases() as $pole) {
            $poles[$pole->value] = PoleElectoral::updateOrCreate(['code' => $pole], ['libelle' => $pole->libelle()]);
        }

        foreach (CodeTypeStructure::cases() as $type) {
            TypeStructure::updateOrCreate(['code' => $type], [
                'libelle' => $type->libelle(),
                'pole_electoral_id' => $poles[$type->pole()->value]->id,
            ]);
        }

        foreach (self::DEPARTEMENTS as $code => $libelle) {
            Departement::updateOrCreate(['code' => $code], ['libelle' => $libelle]);
        }
    }
}
