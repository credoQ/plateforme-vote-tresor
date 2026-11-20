<?php

namespace App\Actions\Editions;

use App\Actions\Audit\Journaliser;
use App\Enums\Audit\ActionAuditee;
use App\Enums\Editions\SourceFemmeModele;
use App\Models\Agent;
use App\Models\FemmeModele;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ajoute à l'historique une femme modèle d'une édition antérieure à la plateforme (R59), saisie par le comité.
 *
 * Le matricule et l'année sont obligatoires ; sans nom, celui de la fiche agent est repris. Une femme modèle déjà
 * proclamée par la plateforme pour cette année ne se remplace pas ; une ligne saisie ou importée est mise à jour.
 */
class AjouterFemmeModele
{
    public const ANNEE_MINIMALE = 1990;

    public function __construct(private Journaliser $journal) {}

    /**
     * @param  array{matricule: string, annee: int|string|null, nom?: string|null, scrutin?: string|null}  $donnees
     *
     * @throws ValidationException
     */
    public function handle(array $donnees): FemmeModele
    {
        $matricule = trim($donnees['matricule']);
        $annee = (int) $donnees['annee'];
        $erreurs = [];

        if ($matricule === '') {
            $erreurs['matricule'] = 'Saisissez le matricule.';
        }
        if ($annee < self::ANNEE_MINIMALE || $annee > (int) now()->year) {
            $erreurs['annee'] = 'Saisissez une année entre '.self::ANNEE_MINIMALE.' et '.now()->year.'.';
        }
        if ($erreurs !== []) {
            throw ValidationException::withMessages($erreurs);
        }

        $agent = Agent::where('matricule', $matricule)->first();
        $nom = trim((string) ($donnees['nom'] ?? '')) ?: $agent?->nomComplet();
        if ($nom === null || $nom === '') {
            throw ValidationException::withMessages(['nom' => "Aucune agent de matricule « {$matricule} » dans la liste des agents : saisissez son nom."]);
        }

        $existante = FemmeModele::where('matricule', $matricule)->where('annee', $annee)->first();
        if ($existante?->source === SourceFemmeModele::Plateforme) {
            throw ValidationException::withMessages(['matricule' => "Cette agent est déjà femme modèle {$annee}, proclamée par la plateforme."]);
        }

        $scrutin = trim((string) ($donnees['scrutin'] ?? ''));

        return DB::transaction(function () use ($matricule, $annee, $agent, $nom, $scrutin): FemmeModele {
            $femmeModele = FemmeModele::updateOrCreate(['matricule' => $matricule, 'annee' => $annee], [
                'agent_id' => $agent?->id,
                'nom_complet' => mb_substr($nom, 0, 200),
                'scrutin' => $scrutin !== '' ? mb_substr($scrutin, 0, 200) : null,
                'source' => SourceFemmeModele::Saisie,
            ]);
            $this->journal->handle(ActionAuditee::FemmeModeleAjoutee, $femmeModele, ['matricule' => $matricule, 'annee' => $annee]);

            return $femmeModele;
        });
    }
}
