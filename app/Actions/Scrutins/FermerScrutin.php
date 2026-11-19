<?php

namespace App\Actions\Scrutins;

use App\Actions\Audit\Journaliser;
use App\Enums\Audit\ActionAuditee;
use App\Enums\Scrutins\StatutScrutin;
use App\Models\Scrutin;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ferme un scrutin ouvert à sa date-heure de fermeture (R39). Le dépouillement suit (module 07).
 */
class FermerScrutin
{
    public function __construct(private Journaliser $journal) {}

    /**
     * @throws ValidationException
     */
    public function handle(Scrutin $scrutin): Scrutin
    {
        return DB::transaction(function () use ($scrutin): Scrutin {
            $scrutin = Scrutin::lockForUpdate()->findOrFail($scrutin->id);

            if ($scrutin->statut !== StatutScrutin::Ouvert) {
                throw ValidationException::withMessages(['scrutin' => 'Seul un scrutin ouvert peut être fermé.']);
            }

            if ($scrutin->date_heure_fermeture->isFuture()) {
                throw ValidationException::withMessages(['scrutin' => 'Le scrutin n’a pas atteint sa date-heure de fermeture.']);
            }

            $scrutin->update(['statut' => StatutScrutin::Clos]);
            $this->journal->handle(ActionAuditee::ScrutinFerme, $scrutin, ['scrutin' => $scrutin->code]);

            return $scrutin;
        });
    }
}
