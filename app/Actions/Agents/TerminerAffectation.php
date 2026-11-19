<?php

namespace App\Actions\Agents;

use App\Models\Affectation;
use App\Models\Agent;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Clôt l'affectation en cours d'un agent qui quitte la DGTCP (départ, retraite, fin de détachement).
 * Sans affectation en cours, l'agent n'est plus en service (R13).
 */
class TerminerAffectation
{
    /**
     * @throws ValidationException
     */
    public function handle(Agent $agent, CarbonInterface $dateFin): Affectation
    {
        $enCours = $agent->affectations()->whereNull('date_fin')->first();

        if ($enCours === null) {
            throw ValidationException::withMessages(['agent_id' => "L’agent {$agent->matricule} n’a aucune affectation en cours."]);
        }

        if ($dateFin->toDateString() < $enCours->date_debut->toDateString()) {
            throw ValidationException::withMessages(['date_fin' => 'La date de fin ne peut pas précéder le début de l’affectation.']);
        }

        $enCours->update(['date_fin' => $dateFin->toDateString()]);

        return $enCours;
    }
}
