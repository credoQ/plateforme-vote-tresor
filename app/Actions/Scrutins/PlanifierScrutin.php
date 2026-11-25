<?php

namespace App\Actions\Scrutins;

use App\Enums\Scrutins\StatutScrutin;
use App\Models\Scrutin;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fixe la date-heure d'ouverture et de fermeture d'un scrutin (R19), dans les dates de sa phase (comparées à
 * l'heure du Bénin), avec une ouverture à venir : un scrutin ne s'ouvre jamais par surprise.
 */
class PlanifierScrutin
{
    /**
     * @throws ValidationException
     */
    public function handle(Scrutin $scrutin, CarbonInterface $ouverture, CarbonInterface $fermeture): Scrutin
    {
        if (! in_array($scrutin->statut, [StatutScrutin::EnPreparation, StatutScrutin::ListesArretees], true)) {
            $this->echouer('scrutin', 'Un scrutin ouvert, clos ou annulé ne peut plus être replanifié.');
        }

        if ($ouverture->greaterThanOrEqualTo($fermeture)) {
            $this->echouer('scrutin', 'La fermeture doit suivre l’ouverture du scrutin.');
        }

        if ($ouverture->isPast()) {
            $this->echouer('scrutin', 'L’ouverture est déjà passée : choisissez une date et une heure à venir.');
        }

        $phase = $scrutin->phase;
        $jourOuverture = $ouverture->copy()->setTimezone(Scrutin::FUSEAU)->toDateString();
        $jourFermeture = $fermeture->copy()->setTimezone(Scrutin::FUSEAU)->toDateString();
        if ($phase->date_debut !== null && $jourOuverture < $phase->date_debut->toDateString()) {
            $this->echouer('scrutin', "Le scrutin ne peut pas s’ouvrir avant le début de la phase, le {$phase->date_debut->format('d/m/Y')}.");
        }

        if ($phase->date_fin !== null && $jourFermeture > $phase->date_fin->toDateString()) {
            $this->echouer('scrutin', "Le scrutin doit fermer au plus tard à la fin de la phase, le {$phase->date_fin->format('d/m/Y')}.");
        }

        // Verrou du scrutin : la planification ne peut pas croiser son ouverture automatique.
        DB::transaction(function () use ($scrutin, $ouverture, $fermeture): void {
            $verrouille = Scrutin::lockForUpdate()->findOrFail($scrutin->id);
            if (! in_array($verrouille->statut, [StatutScrutin::EnPreparation, StatutScrutin::ListesArretees], true)) {
                $this->echouer('scrutin', 'Un scrutin ouvert, clos ou annulé ne peut plus être replanifié.');
            }

            $verrouille->update(['date_heure_ouverture' => $ouverture, 'date_heure_fermeture' => $fermeture]);
        });

        return $scrutin->refresh();
    }

    /**
     * @throws ValidationException
     */
    private function echouer(string $champ, string $message): never
    {
        throw ValidationException::withMessages([$champ => $message]);
    }
}
