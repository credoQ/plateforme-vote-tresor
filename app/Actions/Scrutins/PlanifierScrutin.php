<?php

namespace App\Actions\Scrutins;

use App\Enums\Scrutins\StatutScrutin;
use App\Models\Scrutin;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Fixe la date-heure d'ouverture et de fermeture d'un scrutin (R19), dans les dates de sa phase.
 */
class PlanifierScrutin
{
    /**
     * @throws ValidationException
     */
    public function handle(Scrutin $scrutin, CarbonInterface $ouverture, CarbonInterface $fermeture): Scrutin
    {
        if (! in_array($scrutin->statut, [StatutScrutin::EnPreparation, StatutScrutin::ListesArretees], true)) {
            $this->echouer('date_heure_ouverture', 'Un scrutin ouvert, clos ou annulé ne peut plus être replanifié.');
        }

        if ($ouverture->greaterThanOrEqualTo($fermeture)) {
            $this->echouer('date_heure_fermeture', 'La fermeture doit suivre l’ouverture du scrutin.');
        }

        $phase = $scrutin->phase;
        if ($phase->date_debut !== null && $ouverture->toDateString() < $phase->date_debut->toDateString()) {
            $this->echouer('date_heure_ouverture', "Le scrutin ne peut pas s’ouvrir avant le début de la phase, le {$phase->date_debut->format('d/m/Y')}.");
        }

        if ($phase->date_fin !== null && $fermeture->toDateString() > $phase->date_fin->toDateString()) {
            $this->echouer('date_heure_fermeture', "Le scrutin doit fermer au plus tard à la fin de la phase, le {$phase->date_fin->format('d/m/Y')}.");
        }

        $scrutin->update(['date_heure_ouverture' => $ouverture, 'date_heure_fermeture' => $fermeture]);

        return $scrutin;
    }

    /**
     * @throws ValidationException
     */
    private function echouer(string $champ, string $message): never
    {
        throw ValidationException::withMessages([$champ => $message]);
    }
}
