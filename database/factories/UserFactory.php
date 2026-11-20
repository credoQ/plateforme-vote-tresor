<?php

namespace Database\Factories;

use App\Enums\Acces\CodeRole;
use App\Models\Agent;
use App\Models\Role;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'login' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Utilisateur muni des rôles donnés (les rôles de référence sont créés au besoin).
     */
    public function avecRoles(CodeRole ...$roles): static
    {
        return $this->afterCreating(function (User $user) use ($roles): void {
            $user->roles()->attach(collect($roles)->map(fn (CodeRole $role): int => Role::firstOrCreate(
                ['code' => $role->value],
                ['libelle' => $role->libelle()],
            )->id));
        });
    }

    public function pourAgent(Agent $agent): static
    {
        return $this->state(fn (): array => ['agent_id' => $agent->id, 'login' => $agent->matricule, 'name' => $agent->nomComplet()]);
    }

    public function pointFocalDe(Structure $structure): static
    {
        return $this->state(fn (): array => ['structure_id' => $structure->id])->avecRoles(CodeRole::PointFocal);
    }

    public function inactif(): static
    {
        return $this->state(fn (): array => ['actif' => false]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
