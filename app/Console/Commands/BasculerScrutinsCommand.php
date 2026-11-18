<?php

namespace App\Console\Commands;

use App\Actions\Scrutins\FermerScrutin;
use App\Actions\Scrutins\OuvrirScrutin;
use App\Enums\Editions\StatutPhase;
use App\Enums\Scrutins\StatutScrutin;
use App\Models\Scrutin;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('scrutins:basculer')]
#[Description('Ouvre et ferme les scrutins arrivés à leur date-heure (R38, R39), lancé chaque minute par le planificateur')]
class BasculerScrutinsCommand extends Command
{
    public function handle(OuvrirScrutin $ouvrir, FermerScrutin $fermer): int
    {
        $aOuvrir = Scrutin::query()
            ->where('statut', StatutScrutin::ListesArretees->value)
            ->where('date_heure_ouverture', '<=', now())
            ->whereRelation('phase', 'statut', StatutPhase::Ouverte->value)
            ->get();

        foreach ($aOuvrir as $scrutin) {
            $scrutin = $ouvrir->handle($scrutin);
            $this->components->info("{$scrutin->libelle} : ".mb_strtolower($scrutin->statut->libelle()).'.');
        }

        $aFermer = Scrutin::query()
            ->where('statut', StatutScrutin::Ouvert->value)
            ->where('date_heure_fermeture', '<=', now())
            ->get();

        foreach ($aFermer as $scrutin) {
            $fermer->handle($scrutin);
            $this->components->info("{$scrutin->libelle} : clos.");
        }

        return self::SUCCESS;
    }
}
