<?php

namespace App\Actions\Scrutins;

use App\Actions\Audit\Journaliser;
use App\Enums\Audit\ActionAuditee;
use App\Enums\Scrutins\StatutScrutin;
use App\Models\Scrutin;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rouvre les listes arrêtées d'un scrutin sur décision motivée du comité, tracée au journal (R27).
 *
 * Les listes restent calculées depuis les agents et leurs affectations à la date de gel : la réouverture permet de
 * corriger ces données, d'actualiser les listes puis de les arrêter de nouveau. Les désactivations de candidates sont
 * conservées (R33). Impossible une fois le scrutin ouvert ; tant que les listes ne sont pas de nouveau arrêtées,
 * le scrutin ne s'ouvre pas.
 */
class RouvrirListes
{
    public function __construct(private Journaliser $journal) {}

    /**
     * @throws ValidationException
     */
    public function handle(Scrutin $scrutin, string $motif): Scrutin
    {
        return DB::transaction(function () use ($scrutin, $motif): Scrutin {
            $scrutin = Scrutin::lockForUpdate()->findOrFail($scrutin->id);

            if (! $scrutin->listesPeuventEtreRouvertes()) {
                throw ValidationException::withMessages(['scrutin' => 'Seules des listes arrêtées, avant l’ouverture du scrutin, peuvent être rouvertes (R27).']);
            }

            $scrutin->update(['statut' => StatutScrutin::EnPreparation]);
            $this->journal->handle(ActionAuditee::ListesRouvertes, $scrutin, [
                'scrutin' => $scrutin->code,
                'motif' => $motif,
                'inscrits' => $scrutin->inscriptions()->count(),
                'candidates' => $scrutin->candidates()->count(),
            ]);

            return $scrutin;
        });
    }
}
