<?php

namespace Database\Seeders;

use App\Enums\Referentiel\CodeTypeStructure;
use App\Models\Structure;
use App\Models\TypeStructure;
use Illuminate\Database\Seeder;

/**
 * Structures centrales de la DGTCP (AOF, arrêté n° 125 du 21 janvier 2022) : Cabinet, structures rattachées
 * au Directeur général et directions techniques, dont la DPCP et la TGE. Toutes relèvent du pôle structures (R7)
 * et constituent chacune leur propre structure électorale (R10).
 *
 * Idempotent : les lignes sont repérées par leur code. À exécuter après ReferentielSeeder.
 */
class StructuresCentralesSeeder extends Seeder
{
    /**
     * Code => [sigle, libellé, type, rattachée au Cabinet].
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
    ];

    public function run(): void
    {
        $types = TypeStructure::all()->mapWithKeys(fn (TypeStructure $type): array => [$type->code->value => $type->id]);
        $cabinet = null;

        foreach (self::STRUCTURES as $code => [$sigle, $libelle, $type, $rattacheeAuCabinet]) {
            $structure = Structure::updateOrCreate(['code' => $code], [
                'sigle' => $sigle,
                'libelle' => $libelle,
                'niveau' => Structure::NIVEAU_STRUCTURE_ELECTORALE,
                'type_structure_id' => $types[$type->value],
                'structure_parente_id' => $rattacheeAuCabinet ? $cabinet?->id : null,
            ]);
            $cabinet ??= $structure;
        }
    }
}
