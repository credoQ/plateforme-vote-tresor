<?php

namespace App\Actions\Editions;

use App\Actions\Audit\Journaliser;
use App\Actions\Documents\LireTableau;
use App\Enums\Audit\ActionAuditee;
use App\Enums\Editions\SourceFemmeModele;
use App\Models\Agent;
use App\Models\FemmeModele;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Importe les femmes modèles des éditions antérieures à la plateforme (R59), depuis un fichier Excel ou CSV.
 *
 * Colonnes : matricule et annee (obligatoires), nom et scrutin (facultatifs ; sans nom, celui de la fiche agent).
 * Une ligne est repérée par son matricule et son année : elle est créée ou mise à jour. Une ligne incomplète,
 * ou déjà proclamée par la plateforme, est rejetée sans bloquer les autres.
 */
class ImporterFemmesModeles
{
    /**
     * @var list<string>
     */
    public const COLONNES_OBLIGATOIRES = ['matricule', 'annee'];

    public function __construct(private LireTableau $lecteur, private Journaliser $journal) {}

    /**
     * @throws InvalidArgumentException si le fichier est illisible ou si une colonne obligatoire manque
     */
    public function handle(string $chemin): RapportImportFemmesModeles
    {
        $rapport = new RapportImportFemmesModeles;
        $lignes = iterator_to_array($this->lecteur->handle($chemin, self::COLONNES_OBLIGATOIRES), true);

        DB::transaction(function () use ($lignes, $rapport): void {
            foreach ($lignes as $numero => $ligne) {
                $motif = $this->enregistrer($ligne);
                if ($motif === null) {
                    $rapport->enregistrees++;
                } else {
                    $rapport->rejets[$numero] = $motif;
                }
            }

            $this->journal->handle(ActionAuditee::ImportFemmesModeles, null, ['enregistrees' => $rapport->enregistrees, 'rejetees' => count($rapport->rejets)]);
        });

        return $rapport;
    }

    /**
     * Enregistre une ligne ; renvoie le motif de rejet, ou null.
     *
     * @param  array<string, string>  $ligne
     */
    private function enregistrer(array $ligne): ?string
    {
        $matricule = trim($ligne['matricule']);
        $annee = trim($ligne['annee']);

        if ($matricule === '') {
            return 'Matricule absent.';
        }

        if (preg_match('/^\d{4}$/', $annee) !== 1 || (int) $annee < 1990 || (int) $annee > (int) now()->year) {
            return "L’année « {$annee} » n’est pas une année valide.";
        }

        $agent = Agent::where('matricule', $matricule)->first();
        $nom = trim($ligne['nom'] ?? '') ?: $agent?->nomComplet();
        if ($nom === null || $nom === '') {
            return "Nom absent, et aucune agent de matricule « {$matricule} » dans la liste des agents.";
        }

        $existante = FemmeModele::where('matricule', $matricule)->where('annee', (int) $annee)->first();
        if ($existante?->source === SourceFemmeModele::Plateforme) {
            return "Femme modèle {$annee} déjà proclamée par la plateforme.";
        }

        FemmeModele::updateOrCreate(['matricule' => $matricule, 'annee' => (int) $annee], [
            'agent_id' => $agent?->id,
            'nom_complet' => mb_substr($nom, 0, 200),
            'scrutin' => ($ligne['scrutin'] ?? '') !== '' ? mb_substr(trim($ligne['scrutin']), 0, 200) : null,
            'source' => SourceFemmeModele::Import,
        ]);

        return null;
    }
}
