<?php

namespace App\Actions\Scrutins;

use App\Enums\Editions\StatutPhase;
use App\Enums\Scrutins\StatutScrutin;
use App\Models\Scrutin;
use Illuminate\Validation\ValidationException;

/**
 * Recalcule les listes de tous les scrutins dont les listes ne sont pas encore arrêtées (R27), après une correction
 * d'un agent, d'une affectation ou d'une structure : les listes suivent les données jusqu'à leur arrêt, sans
 * action du comité. Les listes arrêtées ne bougent pas (R30).
 */
class ActualiserListesModifiables
{
    public function __construct(private ConstituerListes $constituerListes) {}

    /**
     * @return int Nombre de scrutins actualisés.
     */
    public function handle(): int
    {
        $actualises = 0;

        Scrutin::query()
            ->where('statut', StatutScrutin::EnPreparation->value)
            ->whereHas('phase', fn ($phase) => $phase->where('statut', '!=', StatutPhase::Cloturee->value))
            // Un scrutin sans zone de vote (structures couvertes) n'a pas de liste à recalculer.
            ->whereHas('structures')
            ->with('phase')
            ->each(function (Scrutin $scrutin) use (&$actualises): void {
                try {
                    $this->constituerListes->handle($scrutin);
                    $actualises++;
                } catch (ValidationException) {
                    // Listes arrêtées entre-temps : elles restent telles quelles.
                }
            });

        return $actualises;
    }
}
