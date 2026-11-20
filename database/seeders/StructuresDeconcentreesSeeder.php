<?php

namespace Database\Seeders;

use App\Enums\Referentiel\CodeTypeStructure;
use App\Models\Departement;
use App\Models\Structure;
use App\Models\TypeStructure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Structures déconcentrées de la DGTCP : le réseau comptable des services extérieurs du Trésor
 * (AOF, arrêté n° 125 du 21 janvier 2022, chapitre 2, section 5).
 *
 * - Pôle départemental (R7, R9) : une Trésorerie départementale par département et une Trésorerie communale
 *   par commune (article 78), rattachée à la TD de son département (R8).
 * - Pôle structures (R7, R10) : les Trésoreries des Ministères et Institutions de l'État (TMIE) de l'export SIGFP
 *   et l'Agence comptable de la Dette, structures électorales autonomes sans rattachement.
 *
 * Idempotent : les lignes sont repérées par leur code. À exécuter après ReferentielSeeder.
 */
class StructuresDeconcentreesSeeder extends Seeder
{
    /**
     * Trésoreries départementales : code du département => libellé.
     *
     * @var array<string, string>
     */
    public const TRESORERIES_DEPARTEMENTALES = [
        'ALI' => "Trésorerie départementale de l'Alibori",
        'ATA' => "Trésorerie départementale de l'Atacora",
        'ATL' => "Trésorerie départementale de l'Atlantique",
        'BOR' => 'Trésorerie départementale du Borgou',
        'COL' => 'Trésorerie départementale des Collines',
        'COU' => 'Trésorerie départementale du Couffo',
        'DON' => 'Trésorerie départementale de la Donga',
        'LIT' => 'Trésorerie départementale du Littoral',
        'MON' => 'Trésorerie départementale du Mono',
        'OUE' => "Trésorerie départementale de l'Ouémé",
        'PLA' => 'Trésorerie départementale du Plateau',
        'ZOU' => 'Trésorerie départementale du Zou',
    ];

    /**
     * TMIE de l'export SIGFP des postes comptables : code SIGFP => [sigle, libellé].
     *
     * @var array<string, array{string, string}>
     */
    public const TMIE = [
        'TFI0003' => ['MEF', "TMIE Ministère de l'Economie et des Finances"],
        'TFI0004' => ['MAEC', 'TMIE MAEC'],
        'TFI0005' => ['MD', 'TMIE Ministère de la Défense'],
        'TFI0007' => ['MEMP', 'TMIE MEMP'],
        'TFI0008' => ['MESRS', 'TMIE MESRS'],
        'TFI0009' => ['MESTFP', 'TMIE MESTFP'],
        'TFI0010' => ['MIT', 'TMIE MIT'],
        'TFI0011' => ['MDGL', 'TMIE Ministère de la Décentralisation et de la Gouvernance Locale'],
        'TFI0013' => ['MPMEPE', 'TMIE MPMEPE'],
        'TFI0014' => ['MIC', 'TMIE MIC'],
        'TFI0015' => ['MTFP', 'TMIE MTFP'],
        'TFI0016' => ['MS', 'TMIE Ministère de la Santé'],
        'TFI0017' => ['MPD', 'TMIE MPD'],
        'TFI0018' => ['MJL', 'TMIE MJL'],
        'TFI0020' => ['MISP', 'TMIE MISP'],
        'TFI0021' => ['MASM', 'TMIE MASM'],
        'TFI0022' => ['MAEP', 'TMIE MAEP'],
        'TFI0023' => ['MCVDD', 'TMIE MCVDD'],
        'TFI0024' => ['MEM', 'TMIE MEM'],
        'TFI1300' => ['ME', "TMIE Ministère de l'Energie"],
        'TFI1301' => ['MEEM', 'TMIE MEEM'],
        'TFI1302' => ['MND', 'TMIE Ministère du Numérique et de la Digitalisation'],
        'TFI1303' => ['HCJ', 'TMIE Haute Cour de Justice'],
        'TFI1304' => ['CS', 'TMIE Cour Suprême'],
        'TFI1305' => ['MR', 'TMIE Médiateur de la République'],
        'TFI1306' => ['HAAC', "TMIE Haute Autorité de l'Audiovisuelle et de la Communication"],
        'TFI1307' => ['APDP', 'TMIE Autorité de Protection des Données à Caractère Personnel'],
        'TFI1308' => ['AN', 'TMIE Assemblée Nationale'],
        'TFI1309' => ['CENA', 'TMIE Commission Électorale Nationale Autonome'],
        'TFI1310' => ['CC', 'TMIE Cour Constitutionnelle'],
        'TFI1311' => ['CES', 'TMIE Conseil Economique et Social'],
        'TFI1312' => ['SPORTS', 'TMIE Ministère des Sports'],
        'TFI1313' => ['MCVT', 'TMIE MCVT'],
        'TFI1314' => ['MTCA', 'TMIE Ministère du Tourisme, de la Culture et des Arts'],
        'TFI1315' => ['PR', 'TMIE Présidence de la République'],
        'TFI1316' => ['CBDH', "TMIE Commission Béninoise des Droits de l'Homme"],
        'TFI1317' => ['CDC', 'TMIE Cour des comptes'],
    ];

    /**
     * Les 77 communes du Bénin, par code de département.
     *
     * @var array<string, list<string>>
     */
    public const COMMUNES = [
        'ALI' => ['Banikoara', 'Gogounou', 'Kandi', 'Karimama', 'Malanville', 'Ségbana'],
        'ATA' => ['Boukoumbé', 'Cobly', 'Kérou', 'Kouandé', 'Matéri', 'Natitingou', 'Péhunco', 'Tanguiéta', 'Toucountouna'],
        'ATL' => ['Abomey-Calavi', 'Allada', 'Kpomassè', 'Ouidah', 'Sô-Ava', 'Toffo', 'Tori-Bossito', 'Zè'],
        'BOR' => ['Bembèrèkè', 'Kalalé', "N'Dali", 'Nikki', 'Parakou', 'Pèrèrè', 'Sinendé', 'Tchaourou'],
        'COL' => ['Bantè', 'Dassa-Zoumè', 'Glazoué', 'Ouèssè', 'Savalou', 'Savè'],
        'COU' => ['Aplahoué', 'Djakotomey', 'Dogbo', 'Klouékanmè', 'Lalo', 'Toviklin'],
        'DON' => ['Bassila', 'Copargo', 'Djougou', 'Ouaké'],
        'LIT' => ['Cotonou'],
        'MON' => ['Athiémé', 'Bopa', 'Comè', 'Grand-Popo', 'Houéyogbé', 'Lokossa'],
        'OUE' => ['Adjarra', 'Adjohoun', 'Aguégués', 'Akpro-Missérété', 'Avrankou', 'Bonou', 'Dangbo', 'Porto-Novo', 'Sèmè-Kpodji'],
        'PLA' => ['Adja-Ouèrè', 'Ifangni', 'Kétou', 'Pobè', 'Sakété'],
        'ZOU' => ['Abomey', 'Agbangnizoun', 'Bohicon', 'Covè', 'Djidja', 'Ouinhi', 'Za-Kpota', 'Zagnanado', 'Zogbodomey'],
    ];

    public function run(): void
    {
        $types = TypeStructure::all()->mapWithKeys(fn (TypeStructure $type): array => [$type->code->value => $type->id]);
        $departements = Departement::pluck('id', 'code');

        foreach (self::TRESORERIES_DEPARTEMENTALES as $codeDepartement => $libelle) {
            $td = Structure::updateOrCreate(['code' => 'TD-'.$codeDepartement], [
                'sigle' => 'TD',
                'libelle' => $libelle,
                'niveau' => Structure::NIVEAU_STRUCTURE_ELECTORALE,
                'type_structure_id' => $types[CodeTypeStructure::TresorerieDepartementale->value],
                'departement_id' => $departements[$codeDepartement],
            ]);

            foreach (self::COMMUNES[$codeDepartement] as $commune) {
                Structure::updateOrCreate(['code' => self::codeTresorerieCommunale($commune)], [
                    'sigle' => 'TC',
                    'libelle' => 'Trésorerie communale '.self::complementDeLieu($commune),
                    'niveau' => Structure::NIVEAU_STRUCTURE_ELECTORALE,
                    'type_structure_id' => $types[CodeTypeStructure::TresorerieCommunale->value],
                    'departement_id' => $departements[$codeDepartement],
                    'structure_parente_id' => $td->id,
                ]);
            }
        }

        foreach (self::TMIE as [$sigle, $libelle]) {
            Structure::updateOrCreate(['code' => 'TMIE-'.$sigle], [
                'sigle' => 'TMIE '.$sigle,
                'libelle' => $libelle,
                'niveau' => Structure::NIVEAU_STRUCTURE_ELECTORALE,
                'type_structure_id' => $types[CodeTypeStructure::Tmie->value],
            ]);
        }

        Structure::updateOrCreate(['code' => 'ACD'], [
            'sigle' => 'ACD',
            'libelle' => 'Agence comptable de la Dette',
            'niveau' => Structure::NIVEAU_STRUCTURE_ELECTORALE,
            'type_structure_id' => $types[CodeTypeStructure::Acd->value],
        ]);
    }

    public static function codeTresorerieCommunale(string $commune): string
    {
        return 'TC-'.Str::upper(Str::slug($commune));
    }

    /**
     * « de Ouidah », « d'Abomey », « des Aguégués ».
     */
    private static function complementDeLieu(string $commune): string
    {
        if ($commune === 'Aguégués') {
            return 'des Aguégués';
        }

        $initiale = Str::lower(Str::ascii(mb_substr($commune, 0, 2)));

        return in_array($initiale[0], ['a', 'e', 'i', 'o', 'u'], true) && $initiale !== 'ou' ? "d'{$commune}" : "de {$commune}";
    }
}
