<?php

namespace App\Console\Commands;

use App\Actions\Acces\GenererComptesAgents;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('acces:generer-comptes')]
#[Description('Crée un compte électeur pour chaque agent en service sans compte et liste les agents sans adresse e-mail')]
class GenererComptesAgentsCommand extends Command
{
    public function handle(GenererComptesAgents $generer): int
    {
        ['crees' => $crees, 'sans_email' => $sansEmail] = $generer->handle();

        $this->components->info($crees === 0 ? 'Tous les agents en service ont déjà un compte.' : "{$crees} compte(s) créé(s).");

        if ($sansEmail !== []) {
            $this->components->warn(count($sansEmail).' agent(s) sans adresse e-mail ne pourront pas recevoir de code de connexion :');
            $this->table(['Matricule', 'Nom', 'Structure'], $sansEmail);
        }

        return self::SUCCESS;
    }
}
