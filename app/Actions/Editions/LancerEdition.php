<?php

namespace App\Actions\Editions;

use App\Enums\Editions\StatutEdition;
use App\Models\Edition;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Passe une édition en cours, une fois son calendrier complet (R16) : une seule édition peut être en cours.
 */
class LancerEdition
{
    /**
     * @throws ValidationException
     */
    public function handle(Edition $edition): Edition
    {
        return DB::transaction(function () use ($edition): Edition {
            $edition = Edition::with('phases')->lockForUpdate()->findOrFail($edition->id);

            if ($edition->statut !== StatutEdition::EnPreparation) {
                $this->echouer("L’édition {$edition->annee} a déjà été lancée.");
            }

            $enCours = Edition::enCours()->lockForUpdate()->first();
            if ($enCours !== null) {
                $this->echouer("L’édition {$enCours->annee} est déjà en cours : une seule édition peut être en cours (R16).");
            }

            $nonPlanifiees = $edition->phases->filter(fn ($phase): bool => $phase->date_debut === null || $phase->date_fin === null);
            if ($edition->phases->count() !== 3 || $nonPlanifiees->isNotEmpty()) {
                $this->echouer('Les trois phases doivent être planifiées avant de lancer l’édition (R17).');
            }

            $edition->update(['statut' => StatutEdition::EnCours]);

            return $edition;
        });
    }

    /**
     * @throws ValidationException
     */
    private function echouer(string $message): never
    {
        throw ValidationException::withMessages(['edition' => $message]);
    }
}
