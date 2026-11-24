<?php

namespace App\Actions\Agents;

use App\Actions\Audit\Journaliser;
use App\Actions\Documents\LireTableau;
use App\Enums\Agents\Sexe;
use App\Enums\Audit\ActionAuditee;
use App\Models\Agent;
use App\Models\Structure;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * Importe la liste des agents et de leurs affectations depuis un fichier Excel (.xlsx, première feuille) ou CSV
 * (export du SI RH).
 *
 * Colonnes attendues (en-tête en première ligne, dans n'importe quel ordre ; en CSV, séparateur « ; » ou « , ») :
 * matricule, npi, nom, prenoms, sexe, date_naissance, structure (code), date_debut (de l'affectation), email
 * (obligatoire : il reçoit les codes de connexion), et, à titre informatif, date_prise_service, telephone, fonction, statut.
 *
 * Un agent est repéré par son matricule : il est créé ou mis à jour. Une ligne incomplète (sexe, structure ou
 * adresse e-mail absents), en doublon dans le fichier ou en conflit de NPI est rejetée sans bloquer les autres
 * (R11 à R13). Les agents absents du fichier ne sont pas modifiés.
 */
class ImporterAgents
{
    /**
     * @var list<string>
     */
    public const COLONNES_OBLIGATOIRES = ['matricule', 'nom', 'prenoms', 'sexe', 'structure', 'email'];

    public function __construct(
        private LireTableau $lecteur,
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

        foreach ($this->lecteur->handle($chemin, self::COLONNES_OBLIGATOIRES) as $numero => $ligne) {
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

            if ($ligne['email'] === '') {
                $motifs[] = 'Adresse e-mail absente.';
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
