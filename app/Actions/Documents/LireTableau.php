<?php

namespace App\Actions\Documents;

use DateTimeInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;

/**
 * Lit un tableau importé : classeur Excel (.xlsx, première feuille) ou CSV (séparateur « ; » ou « , »).
 *
 * L'en-tête, en première ligne, est normalisé (sans accents, minuscules, « _ » à la place des espaces) ; ses colonnes
 * obligatoires doivent être présentes. Chaque ligne non vide est rendue sous forme colonne => texte, indexée par son
 * numéro de ligne dans le fichier (l'en-tête est la ligne 1).
 */
class LireTableau
{
    /**
     * @var list<string>
     */
    private array $obligatoires = [];

    /**
     * @param  list<string>  $colonnesObligatoires
     * @return iterable<int, array<string, string>>
     *
     * @throws InvalidArgumentException si le fichier est illisible ou si une colonne obligatoire manque
     */
    public function handle(string $chemin, array $colonnesObligatoires): iterable
    {
        $this->obligatoires = $colonnesObligatoires;

        return $this->lignes($chemin);
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

        $manquantes = array_diff($this->obligatoires, $entete);
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
}
