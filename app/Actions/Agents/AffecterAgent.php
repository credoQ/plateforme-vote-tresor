<?php

namespace App\Actions\Agents;

use App\Models\Affectation;
use App\Models\Agent;
use App\Models\Structure;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Affecte un agent à une structure à partir d'une date (R12).
 *
 * L'affectation en cours est close la veille de la nouvelle : l'agent n'a qu'une structure à une date
 * donnée et l'historique est conservé. Réaffecter l'agent à sa structure actuelle ne change rien.
 */
class AffecterAgent
{
    /**
     * @throws ValidationException
     */
    public function handle(Agent $agent, Structure $structure, CarbonInterface $dateDebut): Affectation
    {
        if (! $structure->actif) {
            $this->echouer('structure_id', "La structure {$structure->code} est inactive : aucun agent ne peut y être affecté.");
        }

        return DB::transaction(function () use ($agent, $structure, $dateDebut): Affectation {
            $jour = $dateDebut->toDateString();
            $affectations = $agent->affectations()->lockForUpdate()->get();

            $enCours = $affectations->firstWhere('date_fin', null);
            if ($enCours?->structure_id === $structure->id) {
                return $enCours;
            }

            // R12 : une affectation ne peut pas commencer avant ou pendant une affectation déjà enregistrée.
            $chevauchement = $affectations->first(fn (Affectation $affectation): bool => $affectation->date_debut->toDateString() >= $jour
                || ($affectation->date_fin !== null && $affectation->date_fin->toDateString() >= $jour));

            if ($chevauchement !== null) {
                $this->echouer('date_debut', "L’affectation du {$dateDebut->format('d/m/Y')} chevauche une affectation déjà enregistrée de l’agent {$agent->matricule} (R12).");
            }

            $enCours?->update(['date_fin' => $dateDebut->copy()->subDay()->toDateString()]);

            return $agent->affectations()->create([
                'structure_id' => $structure->id,
                'date_debut' => $jour,
            ]);
        });
    }

    /**
     * @throws ValidationException
     */
    private function echouer(string $champ, string $message): never
    {
        throw ValidationException::withMessages([$champ => $message]);
    }
}
