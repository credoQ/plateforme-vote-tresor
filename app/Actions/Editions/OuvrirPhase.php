<?php

namespace App\Actions\Editions;

use App\Actions\Audit\Journaliser;
use App\Enums\Audit\ActionAuditee;
use App\Enums\Editions\StatutEdition;
use App\Enums\Editions\StatutPhase;
use App\Enums\Scrutins\StatutScrutin;
use App\Models\Phase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ouvre une phase, dans l'ordre (R17), une fois la phase précédente clôturée,
 * c'est-à-dire tous ses résultats validés et publiés (R18).
 *
 * Les scrutins de la phase s'ouvrent ensuite à leur date-heure d'ouverture.
 */
class OuvrirPhase
{
    public function __construct(private Journaliser $journal) {}

    /**
     * @throws ValidationException
     */
    public function handle(Phase $phase): Phase
    {
        return DB::transaction(function () use ($phase): Phase {
            $phase = Phase::with('edition')->lockForUpdate()->findOrFail($phase->id);

            if ($phase->edition->statut !== StatutEdition::EnCours) {
                $this->echouer('L’édition doit être en cours pour ouvrir une phase (R16).');
            }

            if ($phase->statut !== StatutPhase::Planifiee) {
                $this->echouer("La {$phase->libelle} est déjà ".mb_strtolower($phase->statut->libelle()).'.');
            }

            $precedente = $phase->phasePrecedente();
            if ($precedente !== null && $precedente->statut !== StatutPhase::Cloturee) {
                $this->echouer("La phase {$precedente->numero} doit être clôturée, tous ses résultats validés et publiés, avant d’ouvrir la phase {$phase->numero} (R18).");
            }

            $aDesScrutins = $phase->scrutins()->where('statut', '!=', StatutScrutin::Annule->value)->exists();
            if (! $aDesScrutins) {
                $this->echouer('La phase ne compte aucun scrutin : générez les scrutins avant de l’ouvrir (R19).');
            }

            $phase->update(['statut' => StatutPhase::Ouverte, 'date_ouverture' => now()]);
            $this->journal->handle(ActionAuditee::PhaseOuverte, $phase, ['phase' => $phase->libelle]);

            return $phase;
        });
    }

    /**
     * @throws ValidationException
     */
    private function echouer(string $message): never
    {
        throw ValidationException::withMessages(['phase' => $message]);
    }
}
