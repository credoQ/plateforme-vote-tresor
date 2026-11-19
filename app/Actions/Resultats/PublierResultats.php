<?php

namespace App\Actions\Resultats;

use App\Actions\Audit\Journaliser;
use App\Enums\Audit\ActionAuditee;
use App\Enums\Scrutins\StatutScrutin;
use App\Jobs\Resultats\EnvoyerQualifications;
use App\Jobs\Suivi\EnvoyerAnnonceScrutin;
use App\Models\Scrutin;
use App\Notifications\AnnonceScrutin;
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
    public function __construct(private Journaliser $journal) {}

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
            $this->journal->handle(ActionAuditee::ResultatsPublies, $scrutin, ['scrutin' => $scrutin->code, 'proces_verbal' => $scrutin->procesVerbal->numero]);
            EnvoyerAnnonceScrutin::dispatch($scrutin->id, AnnonceScrutin::PUBLICATION)->afterCommit();
            EnvoyerQualifications::dispatch($scrutin->id)->afterCommit();

            return $scrutin;
        });
    }
}
