<?php

namespace Database\Seeders;

use App\Enums\Referentiel\CodePoleElectoral;
use App\Enums\Referentiel\CodeTypeStructure;
use App\Models\Departement;
use App\Models\PoleElectoral;
use App\Models\Structure;
use App\Models\TypeStructure;
use Illuminate\Database\Seeder;

/**
 * Référentiel de la DGTCP (organisation de 2022) : pôles, types, départements et structures de niveau 1.
 *
 * Idempotent : les lignes sont repérées par leur code. Les TMIE et les Trésoreries communales
 * ne sont pas connues ici ; elles seront importées avec le référentiel complet.
 */
class ReferentielSeeder extends Seeder
{
    /**
     * Départements du Bénin : code => [libellé, libellé de la TD].
     *
     * @var array<string, array{string, string}>
     */
    public const DEPARTEMENTS = [
        'ALI' => ['Alibori', "Trésorerie départementale de l'Alibori"],
        'ATA' => ['Atacora', "Trésorerie départementale de l'Atacora"],
        'ATL' => ['Atlantique', "Trésorerie départementale de l'Atlantique"],
        'BOR' => ['Borgou', 'Trésorerie départementale du Borgou'],
        'COL' => ['Collines', 'Trésorerie départementale des Collines'],
        'COU' => ['Couffo', 'Trésorerie départementale du Couffo'],
        'DON' => ['Donga', 'Trésorerie départementale de la Donga'],
        'LIT' => ['Littoral', 'Trésorerie départementale du Littoral'],
        'MON' => ['Mono', 'Trésorerie départementale du Mono'],
        'OUE' => ['Ouémé', "Trésorerie départementale de l'Ouémé"],
        'PLA' => ['Plateau', 'Trésorerie départementale du Plateau'],
        'ZOU' => ['Zou', 'Trésorerie départementale du Zou'],
    ];

    /**
     * Structures électorales du pôle structures : code => [sigle, libellé, type, rattachée au Cabinet].
     * Le type du Cabinet (Direction centrale) et de l'IGS (Cellule) est une proposition à valider par le métier.
     *
     * @var array<string, array{string|null, string, CodeTypeStructure, bool}>
     */
    public const STRUCTURES = [
        'CAB' => [null, 'Cabinet du Directeur général', CodeTypeStructure::DirectionCentrale, false],
        'IGS' => ['IGS', 'Inspection générale des services', CodeTypeStructure::Cellule, true],
        'CRDQ' => ['CRDQ', 'Cellule des réformes et de la démarche qualité', CodeTypeStructure::Cellule, true],
        'CSSI' => ['CSSI', "Cellule de sécurité des systèmes d'information", CodeTypeStructure::Cellule, true],
        'DAMF' => ['DAMF', 'Direction des Affaires monétaires et financières', CodeTypeStructure::DirectionCentrale, false],
        'DRC' => ['DRC', 'Direction de la Règlementation comptable', CodeTypeStructure::DirectionCentrale, false],
        'DIA' => ['DIA', "Direction de l'Informatique et des Archives", CodeTypeStructure::DirectionCentrale, false],
        'DFPT' => ['DFPT', 'Direction de la Formation professionnelle du Trésor', CodeTypeStructure::DirectionCentrale, false],
        'DMG' => ['DMG', 'Direction des Moyens généraux', CodeTypeStructure::DirectionCentrale, false],
        'DR' => ['DR', 'Direction des Recettes', CodeTypeStructure::DirectionCentrale, false],
        'DPCP' => ['DPCP', 'Direction de la Production des Comptes publics', CodeTypeStructure::DirectionCentrale, false],
        'TGE' => ['TGE', "Trésorerie générale de l'État", CodeTypeStructure::DirectionCentrale, false],
        'ACD' => ['ACD', 'Agence comptable de la Dette', CodeTypeStructure::Acd, false],
    ];

    public function run(): void
    {
        $poles = [];
        foreach (CodePoleElectoral::cases() as $pole) {
            $poles[$pole->value] = PoleElectoral::updateOrCreate(['code' => $pole], ['libelle' => $pole->libelle()]);
        }

        $types = [];
        foreach (CodeTypeStructure::cases() as $type) {
            $types[$type->value] = TypeStructure::updateOrCreate(['code' => $type], [
                'libelle' => $type->libelle(),
                'pole_electoral_id' => $poles[$type->pole()->value]->id,
            ]);
        }

        $cabinet = null;
        foreach (self::STRUCTURES as $code => [$sigle, $libelle, $type, $rattacheeAuCabinet]) {
            $structure = Structure::updateOrCreate(['code' => $code], [
                'sigle' => $sigle,
                'libelle' => $libelle,
                'niveau' => Structure::NIVEAU_STRUCTURE_ELECTORALE,
                'type_structure_id' => $types[$type->value]->id,
                'structure_parente_id' => $rattacheeAuCabinet ? $cabinet?->id : null,
            ]);
            $cabinet ??= $structure;
        }

        foreach (self::DEPARTEMENTS as $code => [$libelle, $libelleTd]) {
            $departement = Departement::updateOrCreate(['code' => $code], ['libelle' => $libelle]);

            Structure::updateOrCreate(['code' => 'TD-'.$code], [
                'sigle' => 'TD',
                'libelle' => $libelleTd,
                'niveau' => Structure::NIVEAU_STRUCTURE_ELECTORALE,
                'type_structure_id' => $types[CodeTypeStructure::TresorerieDepartementale->value]->id,
                'departement_id' => $departement->id,
            ]);
        }
    }
}
