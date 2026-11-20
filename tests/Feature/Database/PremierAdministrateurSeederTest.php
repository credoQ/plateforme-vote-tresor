<?php

namespace Tests\Feature\Database;

use App\Enums\Acces\CodeRole;
use App\Models\User;
use Database\Seeders\PremierAdministrateurSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PremierAdministrateurSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    /**
     * R2 : le compte décrit dans la configuration est créé administrateur, une seule fois.
     */
    public function test_r2_cree_le_premier_administrateur_une_seule_fois(): void
    {
        config(['app.premier_administrateur' => ['login' => '121438', 'nom' => 'Administrateur', 'email' => 'admin@exemple.bj']]);

        $this->seed(PremierAdministrateurSeeder::class);
        $this->seed(PremierAdministrateurSeeder::class);

        $compte = User::sole();
        $this->assertSame('121438', $compte->login);
        $this->assertSame('admin@exemple.bj', $compte->email);
        $this->assertSame([CodeRole::Administrateur->value], $compte->roles->pluck('code')->map->value->all());
    }

    public function test_ne_cree_aucun_compte_sans_configuration(): void
    {
        config(['app.premier_administrateur' => ['login' => null, 'nom' => null, 'email' => null]]);

        $this->seed(PremierAdministrateurSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }
}
