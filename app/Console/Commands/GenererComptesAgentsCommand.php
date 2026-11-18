<?php

namespace App\Console\Commands;

use App\Actions\Acces\GenererComptesAgents;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('acces:generer-comptes')]
#[Description('Crée un compte électeur pour chaque agent en service sans compte et exporte les mots de passe provisoires (CSV)')]
class GenererComptesAgentsCommand extends Command
{
    public function handle(GenererComptesAgents $generer): int
    {
        $comptes = $generer->handle();

        if ($comptes === []) {
            $this->components->info('Tous les agents en service ont déjà un compte.');

            return self::SUCCESS;
        }

        $lignes = ['matricule;nom;structure;mot_de_passe_provisoire'];
        foreach ($comptes as $compte) {
            $lignes[] = implode(';', array_map(fn (string $valeur): string => str_replace(';', ',', $valeur), $compte));
        }

        $chemin = 'comptes/comptes-'.now()->format('Ymd-His').'.csv';
        Storage::disk('local')->put($chemin, "\xEF\xBB\xBF".implode("\n", $lignes)."\n");

        $this->components->info(count($comptes).' compte(s) créé(s).');
        $this->components->warn('Mots de passe provisoires : '.Storage::disk('local')->path($chemin).'. Remettez-les aux points focaux, puis supprimez le fichier.');

        return self::SUCCESS;
    }
}
