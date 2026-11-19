<?php

namespace Database\Factories;

use App\Enums\Editions\Decoupage;
use App\Enums\Scrutins\OrigineCandidate;
use App\Models\College;
use App\Models\Phase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<College>
 */
class CollegeFactory extends Factory
{
    /**
     * Par défaut : l'ensemble de la DGTCP, un seul scrutin, candidates d'office, une élue.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phase_id' => Phase::factory(),
            'pole_electoral_id' => null,
            'decoupage' => Decoupage::Ensemble,
            'origine_candidates' => OrigineCandidate::Automatique,
            'nb_elues' => 1,
        ];
    }
}
