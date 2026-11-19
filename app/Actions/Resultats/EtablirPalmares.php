<?php

namespace App\Actions\Resultats;

use App\Enums\Scrutins\StatutScrutin;
use App\Models\Candidate;
use App\Models\Edition;
use App\Models\Phase;
use Illuminate\Support\Collection;

/**
 * Palmarès d'une édition (R53) : lauréates publiées de chaque phase, par scrutin, et femmes modèles du Trésor
 * (les lauréates de la dernière phase).
 */
class EtablirPalmares
{
    /**
     * @return array{femmes_modeles: Collection<int, Candidate>, phases: Collection<int, array{phase: string, laureates: Collection<int, array{scrutin: string, agent: string, matricule: string, voix: int|null}>}>}
     */
    public function handle(Edition $edition): array
    {
        $phases = $edition->phases()
            ->with(['scrutins' => fn ($query) => $query
                ->where('statut', StatutScrutin::ResultatsPublies->value)
                ->orderBy('code')
                ->with(['laureates.agent'])])
            ->get()
            ->map(fn (Phase $phase): array => [
                'phase' => $phase->libelle,
                'laureates' => $phase->scrutins->flatMap(fn ($scrutin) => $scrutin->laureates->map(fn (Candidate $c): array => [
                    'scrutin' => $scrutin->libelle,
                    'agent' => $c->agent->nomComplet(),
                    'matricule' => $c->agent->matricule,
                    'voix' => $c->nb_voix,
                ]))->values(),
            ]);

        return ['femmes_modeles' => $edition->femmesModeles(), 'phases' => $phases];
    }
}
