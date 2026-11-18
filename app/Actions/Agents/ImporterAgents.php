<?php

namespace App\Actions\Agents;

use App\Enums\Agents\Sexe;
use App\Models\Agent;
use App\Models\Structure;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * Importe la liste des agents et de leurs affectations depuis un fichier CSV (export du SI RH ou d'Excel).
 *
 * Colonnes attendues (en-tête, dans n'importe quel ordre, séparateur « ; » ou « , ») :
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

        return $rapport;
    }

    /**
     * Lit le fichier et renvoie chaque ligne non vide, indexée par son numéro dans le fichier.
     *
     * @return iterable<int, array<string, string>>
     */
    private function lignes(string $chemin): iterable
    {
        $fichier = is_readable($chemin) ? fopen($chemin, 'r') : false;
        if ($fichier === false) {
            throw new InvalidArgumentException("Le fichier {$chemin} est illisible.");
        }

        try {
            $premiere = (string) fgets($fichier);
            $premiere = preg_replace('/^\xEF\xBB\xBF/', '', $premiere);
            $separateur = substr_count($premiere, ';') >= substr_count($premiere, ',') ? ';' : ',';
            $entete = array_map(fn (string $colonne): string => Str::of($colonne)->trim()->ascii()->lower()->replace([' ', '-'], '_')->toString(), str_getcsv($premiere, $separateur, '"', ''));

            $manquantes = array_diff(self::COLONNES_OBLIGATOIRES, $entete);
            if ($manquantes !== []) {
                throw new InvalidArgumentException('Colonnes obligatoires absentes : '.implode(', ', $manquantes).'.');
            }

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
