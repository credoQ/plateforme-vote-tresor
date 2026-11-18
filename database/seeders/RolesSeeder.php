<?php

namespace Database\Seeders;

use App\Enums\Acces\CodeRole;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Les cinq rôles de la plateforme (R2). Idempotent.
 */
class RolesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (CodeRole::cases() as $role) {
            Role::updateOrCreate(['code' => $role->value], ['libelle' => $role->libelle()]);
        }
    }
}
