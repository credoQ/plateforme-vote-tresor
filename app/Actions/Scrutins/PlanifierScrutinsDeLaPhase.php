<?php

namespace App\Actions\Scrutins;

use App\Enums\Scrutins\StatutScrutin;
use App\Models\Phase;
use App\Models\Scrutin;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Planifie d'un coup tous les scrutins encore planifiables d'une phase aux mêmes date-heures d'ouverture et de
 * fermeture (R19), en tout ou rien ; chaque scrutin peut ensuite être ajusté depuis sa fiche (PlanifierScrutin).
 */
class PlanifierScrutinsDeLaPhase
{
    public function __construct(private PlanifierScrutin $planifier) {}

    /**
     * @return int Nombre de scrutins planifiés.
     *
     * @throws ValidationException
     */
    public function handle(Phase $phase, CarbonInterface $ouverture, CarbonInterface $fermeture): int
    {
        return DB::transaction(function () use ($phase, $ouverture, $fermeture): int {
            $scrutins = $phase->scrutins()
                ->whereIn('statut', [StatutScrutin::EnPreparation->value, StatutScrutin::ListesArretees->value])
                ->get();

            if ($scrutins->isEmpty()) {
                throw ValidationException::withMessages(['phase' => 'La phase ne compte aucun scrutin à planifier.']);
            }

            $scrutins->each(fn (Scrutin $scrutin) => $this->planifier->handle($scrutin->setRelation('phase', $phase), $ouverture, $fermeture));

            return $scrutins->count();
        });
    }
}
