<?php

namespace App\Actions\Scrutins;

use App\Enums\Editions\StatutPhase;
use App\Enums\Scrutins\StatutScrutin;
use App\Models\Scrutin;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ouvre un scrutin à sa date-heure d'ouverture (R39), listes arrêtées et phase ouverte.
 * Un scrutin dont toutes les candidates sont désactivées est annulé au lieu d'être ouvert (R38).
 */
class OuvrirScrutin
{
    /**
     * @throws ValidationException
     */
    public function handle(Scrutin $scrutin): Scrutin
    {
        return DB::transaction(function () use ($scrutin): Scrutin {
            $scrutin = Scrutin::with('phase')->lockForUpdate()->findOrFail($scrutin->id);

            if ($scrutin->statut !== StatutScrutin::ListesArretees) {
                $this->echouer('Seul un scrutin dont les listes sont arrêtées peut être ouvert (R27).');
            }

            if ($scrutin->phase->statut !== StatutPhase::Ouverte) {
                $this->echouer('La phase du scrutin doit être ouverte (R18).');
            }

            if ($scrutin->date_heure_ouverture === null || $scrutin->date_heure_ouverture->isFuture()) {
                $this->echouer('Le scrutin n’est pas encore à sa date-heure d’ouverture (R39).');
            }

            $statut = $scrutin->candidates()->actives()->exists() ? StatutScrutin::Ouvert : StatutScrutin::Annule;
            $scrutin->update(['statut' => $statut]);

            return $scrutin;
        });
    }

    /**
     * @throws ValidationException
     */
    private function echouer(string $message): never
    {
        throw ValidationException::withMessages(['scrutin' => $message]);
    }
}
