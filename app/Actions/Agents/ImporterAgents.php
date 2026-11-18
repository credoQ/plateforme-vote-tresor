<?php

namespace App\Actions\Agents;

use App\Actions\Audit\Journaliser;
use App\Enums\Agents\Sexe;
use App\Enums\Audit\ActionAuditee;
use App\Models\Agent;
use App\Models\Structure;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;

/**
 * Importe la liste des agents et de leurs affectations depuis un fichier Excel (.xlsx, première feuille) ou CSV
 * (export du SI RH).
 *
 * Colonnes attendues (en-tête en première ligne, dans n'importe quel ordre ; en CSV, séparateur « ; » ou « , ») :
 * matricule, npi, nom, prenoms, sexe, date_naissance, structure (code), date_debut (de l'affectation),
 * et, à titre informatif, date_prise_service, telephone, email, fonction, statut.
 *
 * Un agent est repéré par son matricule : il est créé ou mis à jour. Une ligne incomplète (sexe ou
 * structure absents), en doublon dans le fichier ou en conflit de NPI est rejetée sans bloquer les autres
 * (R11 à R13). Les agents absents du fichier ne sont pas modifiés.
 */
class ImporterAgents
{
    /**
     * @var list<string>
     */
    public const COLONNES_OBLIGATOIRES = ['matricule', 'nom', 'prenoms', 'sexe', 'structure'];

    public function __construct(
        private EnregistrerAgent $enregistrerAgent,
        private AffecterAgent $affecterAgent,
        private Journaliser $journal,
    ) {}

    /**
     * @param  CarbonInterface|null  $dateEffet  date de début des affectations dont la ligne ne précise pas la date (aujourd'hui par défaut)
     *
     * @throws InvalidArgumentException si le fichier est illisible ou si une colonne obligatoire manque
     */
    public function handle(string $chemin, ?CarbonInterface $dateEffet = null): RapportImport
    {
        $dateEffet ??= Carbon::today();
        $rapport = new RapportImport;
        $structures = Structure::query()->pluck('id', 'code')->mapWithKeys(fn (int $id, string $code) => [mb_strtoupper($code) => $id]);
        $matriculesVus = [];

        foreach ($this->lignes($chemin) as $numero => $ligne) {
            $motifs = [];
            $matricule = $ligne['matricule'];

            if ($matricule !== '' && isset($matriculesVus[$matricule])) {
                $rapport->rejeter($numero, ["Matricule {$matricule} en doublon dans le fichier (déjà présent ligne {$matriculesVus[$matricule]})."]);

                continue;
            }
            $matriculesVus[$matricule] = $numero;

            $sexe = Sexe::depuisSaisie($ligne['sexe']);
            if ($sexe === null) {
                $motifs[] = $ligne['sexe'] === '' ? 'Sexe absent.' : "Sexe « {$ligne['sexe']} » non reconnu (F ou M attendu).";
            }

            $structureId = $structures[mb_strtoupper($ligne['structure'])] ?? null;
            if ($ligne['structure'] === '') {
                $motifs[] = 'Structure d’affectation absente.';
            } elseif ($structureId === null) {
                $motifs[] = "Structure « {$ligne['structure']} » inconnue du référentiel.";
            }

            $dateDebut = $this->date($ligne['date_debut'] ?? '', $motifs, 'date de début d’affectation') ?? $dateEffet;
            $dateNaissance = $this->date($ligne['date_naissance'] ?? '', $motifs, 'date de naissance');
            $datePriseService = $this->date($ligne['date_prise_service'] ?? '', $motifs, 'date de prise de service');

            if ($motifs !== []) {
                $rapport->rejeter($numero, $motifs);

                continue;
            }

            $donnees = [
                'matricule' => $matricule,
                'npi' => $this->valeur($ligne, 'npi'),
                'nom' => $ligne['nom'],
                'prenoms' => $ligne['prenoms'],
                'sexe' => $sexe,
                'date_naissance' => $dateNaissance?->toDateString(),
                'date_prise_service' => $datePriseService?->toDateString(),
                'telephone' => $this->valeur($ligne, 'telephone'),
                'email' => $this->valeur($ligne, 'email'),
                'fonction' => $this->valeur($ligne, 'fonction'),
                'statut' => $this->valeur($ligne, 'statut'),
            ];

            try {
                DB::transaction(function () use ($donnees, $structureId, $dateDebut, $rapport): void {
                    $agent = Agent::where('matricule', $donnees['matricule'])->first();
                    $existait = $agent !== null;

                    $agent = $this->enregistrerAgent->handle($donnees, $agent);

                    $affectation = $this->affecterAgent->handle($agent, Structure::findOrFail($structureId), $dateDebut);

                    $existait ? $rapport->agentsMisAJour++ : $rapport->agentsCrees++;
                    if ($affectation->wasRecentlyCreated) {
                        $rapport->affectationsCreees++;
                    }
                });
            } catch (ValidationException $exception) {
                $rapport->rejeter($numero, array_values(array_merge(...array_values($exception->errors()))));
            }
        }

        $this->journal->handle(ActionAuditee::ImportAgents, null, ['fichier' => basename($chemin), 'crees' => $rapport->agentsCrees, 'mis_a_jour' => $rapport->agentsMisAJour, 'affectations' => $rapport->affectationsCreees, 'rejets' => count($rapport->rejets)]);

        return $rapport;
    }

    /**
     * Lit le fichier et renvoie chaque ligne non vide, indexée par son numéro dans le fichier.
     *
     * @return iterable<int, array<string, string>>
     */
    private function lignes(string $chemin): iterable
    {
        if (! is_readable($chemin)) {
            throw new InvalidArgumentException("Le fichier {$chemin} est illisible.");
        }

        return str_ends_with(mb_strtolower($chemin), '.xlsx') ? $this->lignesExcel($chemin) : $this->lignesCsv($chemin);
    }

    /**
     * @return iterable<int, array<string, string>>
     */
    private function lignesExcel(string $chemin): iterable
    {
        $lecteur = new XlsxReader(new XlsxOptions(SHOULD_PRESERVE_EMPTY_ROWS: true));

        try {
            $lecteur->open($chemin);
        } catch (Throwable) {
            throw new InvalidArgumentException("Le fichier {$chemin} n’est pas un classeur Excel lisible.");
        }

        try {
            foreach ($lecteur->getSheetIterator() as $feuille) {
                $entete = null;
                foreach ($feuille->getRowIterator() as $numero => $ligne) {
                    $valeurs = array_map(fn (mixed $valeur): string => $this->texteCellule($valeur), $ligne->toArray());

                    if ($entete === null) {
                        $entete = $this->entete($valeurs);

                        continue;
                    }

                    if (implode('', $valeurs) === '') {
                        continue;
                    }

                    yield $numero => array_combine($entete, array_pad(array_slice($valeurs, 0, count($entete)), count($entete), ''));
                }

                if ($entete === null) {
                    throw new InvalidArgumentException('Le classeur est vide.');
                }

                break;
            }
        } finally {
            $lecteur->close();
        }
    }

    /**
     * Valeur d'une cellule Excel en texte : dates au format AAAA-MM-JJ, nombres entiers sans décimale (matricule, NPI).
     */
    private function texteCellule(mixed $valeur): string
    {
        return trim(match (true) {
            $valeur instanceof DateTimeInterface => $valeur->format('Y-m-d'),
            is_float($valeur) && floor($valeur) === $valeur => number_format($valeur, 0, '', ''),
            is_bool($valeur) => $valeur ? '1' : '0',
            default => (string) $valeur,
        });
    }

    /**
     * Normalise l'en-tête et vérifie la présence des colonnes obligatoires.
     *
     * @param  list<string>  $colonnes
     * @return list<string>
     */
    private function entete(array $colonnes): array
    {
        $entete = array_map(fn (string $colonne): string => Str::of($colonne)->trim()->ascii()->lower()->replace([' ', '-'], '_')->toString(), $colonnes);

        $manquantes = array_diff(self::COLONNES_OBLIGATOIRES, $entete);
        if ($manquantes !== []) {
            throw new InvalidArgumentException('Colonnes obligatoires absentes : '.implode(', ', $manquantes).'.');
        }

        return $entete;
    }

    /**
     * @return iterable<int, array<string, string>>
     */
    private function lignesCsv(string $chemin): iterable
    {
        $fichier = fopen($chemin, 'r');
        if ($fichier === false) {
            throw new InvalidArgumentException("Le fichier {$chemin} est illisible.");
        }

        try {
            $premiere = (string) fgets($fichier);
            $premiere = preg_replace('/^\xEF\xBB\xBF/', '', $premiere);
            $separateur = substr_count($premiere, ';') >= substr_count($premiere, ',') ? ';' : ',';
            $entete = $this->entete(str_getcsv($premiere, $separateur, '"', ''));

            $numero = 1;
            while (($valeurs = fgetcsv($fichier, null, $separateur, '"', '')) !== false) {
                $numero++;
                if ($valeurs === [null] || implode('', $valeurs) === '') {
                    continue;
                }

                $valeurs = array_map(fn (?string $valeur): string => trim((string) $valeur), array_pad(array_slice($valeurs, 0, count($entete)), count($entete), ''));

                yield $numero => array_combine($entete, $valeurs);
            }
        } finally {
            fclose($fichier);
        }
    }

    /**
     * @param  array<string, string>  $ligne
     */
    private function valeur(array $ligne, string $colonne): ?string
    {
        $valeur = $ligne[$colonne] ?? '';

        return $valeur === '' ? null : $valeur;
    }

    /**
     * Lit une date au format AAAA-MM-JJ ou JJ/MM/AAAA ; une valeur vide donne null.
     *
     * @param  list<string>  $motifs
     */
    private function date(string $valeur, array &$motifs, string $libelle): ?CarbonInterface
    {
        if ($valeur === '') {
            return null;
        }

        // Numéro de série Excel d'une cellule sans format de date (jours depuis le 30/12/1899).
        if (preg_match('/^\d{5}$/', $valeur) === 1) {
            return Carbon::create(1899, 12, 30)->addDays((int) $valeur);
        }

        foreach (['!Y-m-d', '!d/m/Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $valeur);
                if ($date !== null && $date->format(ltrim($format, '!')) === $valeur) {
                    return $date;
                }
            } catch (Throwable) {
                continue;
            }
        }

        $motifs[] = "La {$libelle} « {$valeur} » n’est pas une date valide (AAAA-MM-JJ ou JJ/MM/AAAA).";

        return null;
    }
}
