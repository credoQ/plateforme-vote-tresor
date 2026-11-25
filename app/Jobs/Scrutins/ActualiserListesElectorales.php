<?php

namespace App\Jobs\Scrutins;

use App\Actions\Scrutins\ActualiserListesModifiables;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Mise à jour automatique des listes encore modifiables, lancée après chaque changement d'un agent, d'une
 * affectation ou d'une structure (observateur ActualiserListesSurChangement). Une seule tâche attend à la fois :
 * un import de mille agents ne déclenche qu'un recalcul.
 */
class ActualiserListesElectorales implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    /**
     * Durée maximale du verrou d'unicité, en secondes, si la tâche ne se termine pas.
     */
    public int $uniqueFor = 600;

    public function __construct()
    {
        $this->afterCommit();
    }

    public function handle(ActualiserListesModifiables $actualiser): void
    {
        $actualiser->handle();
    }
}
