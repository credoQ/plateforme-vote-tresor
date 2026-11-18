<?php

namespace App\Actions\Resultats;

use App\Actions\Audit\Journaliser;
use App\Enums\Audit\ActionAuditee;
use App\Enums\Scrutins\StatutScrutin;
use App\Models\Scrutin;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Prolonge un scrutin clos dont le quorum n'est pas atteint (R50) : il est rouvert jusqu'à la nouvelle fermeture
 * et son dépouillement provisoire est abandonné.
 */
class ProlongerScrutin
{
    public function __construct(private Journaliser $journal) {}

    /**
     * @throws ValidationException
     */
    public function handle(Scrutin $scrutin, CarbonInterface $nouvelleFermeture): Scrutin
    {
        return DB::transaction(function () use ($scrutin, $nouvelleFermeture): Scrutin {
            $scrutin = Scrutin::with(['phase', 'procesVerbal'])->lockForUpdate()->findOrFail($scrutin->id);
            $pv = $scrutin->procesVerbal;

            if ($scrutin->statut !== StatutScrutin::Clos || $pv === null || $pv->date_validation !== null) {
                $this->echouer('Seul un scrutin clos, dépouillé et non validé peut être prolongé.');
            }

            if ($pv->quorum_atteint) {
                $this->echouer('Le quorum est atteint : le scrutin ne peut pas être prolongé (R50).');
            }

            if ($nouvelleFermeture->isPast()) {
                $this->echouer('La nouvelle fermeture doit être dans le futur.');
            }

            $finPhase = $scrutin->phase->date_fin;
            if ($finPhase !== null && $nouvelleFermeture->toDateString() > $finPhase->toDateString()) {
                $this->echouer("La prolongation ne peut pas dépasser la fin de la phase, le {$finPhase->format('d/m/Y')}.");
            }

            $pv->delete();
            $scrutin->candidates()->update(['nb_voix' => null, 'pourcentage' => null, 'rang' => null, 'est_laureate' => false]);
            $scrutin->update(['statut' => StatutScrutin::Ouvert, 'date_heure_fermeture' => $nouvelleFermeture]);
            $this->journal->handle(ActionAuditee::ScrutinProlonge, $scrutin, ['scrutin' => $scrutin->code, 'fermeture' => $nouvelleFermeture->toIso8601String()]);

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
