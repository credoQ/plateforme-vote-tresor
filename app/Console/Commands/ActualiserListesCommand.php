<?php

namespace App\Console\Commands;

use App\Actions\Scrutins\ActualiserListesModifiables;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('listes:actualiser')]
#[Description('Met à jour tout de suite les listes électorales et les candidates des scrutins dont les listes ne sont pas encore arrêtées (R27, R35)')]
class ActualiserListesCommand extends Command
{
    public function handle(ActualiserListesModifiables $actualiser): int
    {
        $nombre = $actualiser->handle();
        $this->components->info("{$nombre} scrutin(s) mis à jour.");

        return self::SUCCESS;
    }
}
