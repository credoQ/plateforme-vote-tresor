<?php

namespace Database\Factories;

use App\Enums\Scrutins\OrigineCandidate;
use App\Enums\Scrutins\StatutCandidate;
use App\Models\Agent;
use App\Models\Candidate;
use App\Models\Scrutin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Candidate>
 */
class CandidateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scrutin_id' => Scrutin::factory(),
            'agent_id' => Agent::factory()->femme(),
            'origine' => OrigineCandidate::Automatique,
            'statut' => StatutCandidate::Active,
        ];
    }

    public function laureate(int $rang = 1): static
    {
        return $this->state(fn (): array => ['est_laureate' => true, 'rang' => $rang]);
    }

    public function desactivee(): static
    {
        return $this->state(fn (): array => ['statut' => StatutCandidate::Desactivee, 'date_desistement' => now()]);
    }
}
