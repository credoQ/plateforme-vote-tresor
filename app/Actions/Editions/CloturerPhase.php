<?php

namespace App\Actions\Editions;

use App\Actions\Audit\Journaliser;
use App\Enums\Audit\ActionAuditee;
use App\Enums\Editions\StatutEdition;
use App\Enums\Editions\StatutPhase;
use App\Models\Phase;
use App\Models\Scrutin;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Clôture une phase ouverte quand tous ses scrutins ont leurs résultats validés et publiés, ou sont annulés (R18, R38, R54).
 * La clôture de la finale clôture l'édition (R53).
 */
class CloturerPhase
{
    public function __construct(private Journaliser $journal) {}

    /**
     * @throws ValidationException
     */
    public function handle(Phase $phase): Phase
    {
        return DB::transaction(function () use ($phase): Phase {
            $phase = Phase::with(['edition', 'scrutins'])->lockForUpdate()->findOrFail($phase->id);

            if ($phase->statut !== StatutPhase::Ouverte) {
                throw ValidationException::withMessages(['phase' => "Seule une phase ouverte peut être clôturée ; la {$phase->libelle} est ".mb_strtolower($phase->statut->libelle()).'.']);
            }

            $enAttente = $phase->scrutins->reject(fn (Scrutin $scrutin): bool => $scrutin->statut->estTermine());
            if ($enAttente->isNotEmpty()) {
                throw ValidationException::withMessages(['phase' => 'Les résultats de tous les scrutins doivent être validés et publiés avant la clôture (R18). En attente : '.$enAttente->pluck('libelle')->implode(', ').'.']);
            }

            $phase->update(['statut' => StatutPhase::Cloturee, 'date_cloture' => now()]);
            $this->journal->handle(ActionAuditee::PhaseCloturee, $phase, ['phase' => $phase->libelle]);

            if ($phase->estLaFinale()) {
                $phase->edition->update(['statut' => StatutEdition::Cloturee]);
            }

            return $phase;
        });
    }
}
