<?php

namespace Tests\Feature\Actions\Vote;

use App\Models\Bulletin;
use App\Models\Edition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulerChargeVoteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * R40, R44 : la simulation fait voter chaque électeur une fois par l'action réelle, puis vérifie la chaîne et dépouille.
     */
    public function test_r44_simule_un_scrutin_et_mesure_le_vote(): void
    {
        $this->artisan('vote:simuler-charge', ['--electeurs' => 12, '--candidates' => 3, '--paralleles' => 1, '--force' => true])
            ->expectsOutputToContain('Votes enregistrés')
            ->expectsOutputToContain('intacte')
            ->assertSuccessful();

        $this->assertSame(12, Bulletin::count());
    }

    public function test_refuse_une_base_qui_contient_deja_une_edition(): void
    {
        Edition::factory()->create();

        $this->artisan('vote:simuler-charge', ['--force' => true])
            ->expectsOutputToContain('base dédiée')
            ->assertFailed();
    }

    public function test_refuse_de_tourner_en_production(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->artisan('vote:simuler-charge', ['--force' => true])
            ->expectsOutputToContain('jamais en production')
            ->assertFailed();
    }
}
