<?php

namespace App\Actions\Resultats;

use App\Actions\Audit\Journaliser;
use App\Enums\Audit\ActionAuditee;
use App\Enums\Scrutins\StatutScrutin;
use App\Jobs\Resultats\GenererFichierProcesVerbal;
use App\Models\Edition;
use App\Models\ProcesVerbal;
use App\Models\Scrutin;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Valide les résultats d'un scrutin dépouillé (R51) : le procès-verbal reçoit son numéro et son fichier est généré.
 * Une égalité non départagée bloque la validation (R49).
 */
class ValiderResultats
{
    public function __construct(private Journaliser $journal) {}

    /**
     * @throws ValidationException
     */
    public function handle(Scrutin $scrutin, ?User $auteur = null): ProcesVerbal
    {
        $pv = DB::transaction(function () use ($scrutin, $auteur): ProcesVerbal {
            $scrutin = Scrutin::with(['phase.edition', 'procesVerbal'])->lockForUpdate()->findOrFail($scrutin->id);
            $pv = $scrutin->procesVerbal;

            if ($scrutin->statut !== StatutScrutin::Clos || $pv === null) {
                $this->echouer('Le scrutin doit être clos et dépouillé avant la validation de ses résultats.');
            }

            if ($pv->egalite_a_departager) {
                $this->echouer('Une égalité reste à départager avant la validation.');
            }

            $edition = Edition::lockForUpdate()->findOrFail($scrutin->phase->edition_id);
            $rang = ProcesVerbal::whereNotNull('numero')
                ->whereHas('scrutin.phase', fn ($phase) => $phase->where('edition_id', $edition->id))
                ->count() + 1;

            $pv->update([
                'numero' => sprintf('PV-%d-P%d-%03d', $edition->annee, $scrutin->phase->numero, $rang),
                'date_validation' => now(),
                'valide_par_id' => $auteur?->id,
            ]);
            $scrutin->update(['statut' => StatutScrutin::ResultatsValides]);
            $this->journal->handle(ActionAuditee::ResultatsValides, $scrutin, ['scrutin' => $scrutin->code, 'proces_verbal' => $pv->numero], $auteur);

            return $pv;
        });

        GenererFichierProcesVerbal::dispatch($pv->id)->afterCommit();

        return $pv;
    }

    /**
     * @throws ValidationException
     */
    private function echouer(string $message): never
    {
        throw ValidationException::withMessages(['scrutin' => $message]);
    }
}
