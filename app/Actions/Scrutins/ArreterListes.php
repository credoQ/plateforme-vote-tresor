<?php

namespace App\Actions\Scrutins;

use App\Actions\Audit\Journaliser;
use App\Enums\Audit\ActionAuditee;
use App\Enums\Scrutins\StatutScrutin;
use App\Models\Scrutin;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Arrête la liste électorale et la liste des candidates d'un scrutin (R27, R30), à partir de la date de gel (R14).
 * Les listes sont actualisées une dernière fois, puis figées.
 */
class ArreterListes
{
    public function __construct(private ConstituerListes $constituerListes, private Journaliser $journal) {}

    /**
     * @throws ValidationException
     */
    public function handle(Scrutin $scrutin): Scrutin
    {
        $scrutin->loadMissing('phase.edition');
        $gel = $scrutin->phase->edition->date_gel;

        if (now()->startOfDay()->lt($gel)) {
            $this->echouer("Les listes ne peuvent être arrêtées qu’à partir de la date de gel, le {$gel->format('d/m/Y')}.");
        }

        return DB::transaction(function () use ($scrutin): Scrutin {
            $scrutin = $this->constituerListes->handle(Scrutin::lockForUpdate()->findOrFail($scrutin->id));

            if ($scrutin->inscriptions_count === 0) {
                $this->echouer('La liste électorale est vide : aucun agent en service dans la zone de vote à la date de gel.');
            }

            if ($scrutin->candidates_count === 0) {
                $this->echouer('Le scrutin n’a aucune candidate : nominez-en au moins une, ou, pour des lauréates qualifiées, clôturez d’abord la phase précédente.');
            }

            $scrutin->update(['statut' => StatutScrutin::ListesArretees]);
            $this->journal->handle(ActionAuditee::ListesArretees, $scrutin, ['scrutin' => $scrutin->code, 'inscrits' => $scrutin->inscriptions_count, 'candidates' => $scrutin->candidates_count]);

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
