<?php

namespace App\Actions\Resultats;

use App\Enums\Scrutins\StatutScrutin;
use App\Models\Scrutin;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Publie les résultats validés d'un scrutin, une fois son procès-verbal généré (R51, R54).
 *
 * Les résultats et le procès-verbal deviennent définitifs et archivés (R55) ; les lauréates sont dès lors
 * qualifiées et reprises comme candidates de la phase suivante (R52, R35).
 */
class PublierResultats
{
    /**
     * @throws ValidationException
     */
    public function handle(Scrutin $scrutin): Scrutin
    {
        return DB::transaction(function () use ($scrutin): Scrutin {
            $scrutin = Scrutin::with('procesVerbal')->lockForUpdate()->findOrFail($scrutin->id);

            if ($scrutin->statut !== StatutScrutin::ResultatsValides) {
                throw ValidationException::withMessages(['scrutin' => 'Seuls des résultats validés peuvent être publiés (R51).']);
            }

            if ($scrutin->procesVerbal->fichier === null) {
                throw ValidationException::withMessages(['scrutin' => 'Le procès-verbal n’est pas encore généré : la publication suit sa génération (R51).']);
            }

            $scrutin->procesVerbal->update(['date_publication' => now()]);
            $scrutin->update(['statut' => StatutScrutin::ResultatsPublies]);

            return $scrutin;
        });
    }
}
