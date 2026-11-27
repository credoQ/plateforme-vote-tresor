<?php

namespace App\Jobs\Acces;

use App\Actions\Acces\ArchiverCouponsAcces;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Prépare un paquet de coupons individuels de l'archive ZIP, puis relance la tâche pour le paquet suivant : chaque
 * tâche tient sous le délai du processus de file. Un paquet repris réécrit les mêmes fichiers.
 */
class PreparerArchiveCoupons implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 110;

    /**
     * @param  list<array{compte: int, structure: string, chemin: string}>  $plan
     */
    public function __construct(public string $jeton, public int $auteurId, public array $plan, public int $position = 0) {}

    public function handle(ArchiverCouponsAcces $archiver): void
    {
        $suivante = $archiver->traiterPaquet($this->jeton, $this->auteurId, $this->plan, $this->position);

        if ($suivante !== null) {
            self::dispatch($this->jeton, $this->auteurId, $this->plan, $suivante);
        }
    }

    public function failed(?Throwable $erreur): void
    {
        app(ArchiverCouponsAcces::class)->echouer($this->jeton, $this->auteurId);
    }
}
