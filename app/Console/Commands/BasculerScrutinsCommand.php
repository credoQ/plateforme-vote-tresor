<?php

namespace App\Console\Commands;

use App\Actions\Resultats\DepouillerScrutin;
use App\Actions\Scrutins\FermerScrutin;
use App\Actions\Scrutins\OuvrirScrutin;
use App\Enums\Editions\StatutPhase;
use App\Enums\Scrutins\StatutScrutin;
use App\Models\Scrutin;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

#[Signature('scrutins:basculer')]
#[Description('Ouvre, ferme puis dépouille les scrutins arrivés à leur date-heure (R38, R39, R47), lancé chaque minute par le planificateur')]
class BasculerScrutinsCommand extends Command
{
    public function handle(OuvrirScrutin $ouvrir, FermerScrutin $fermer, DepouillerScrutin $depouiller): int
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

        // R47 : à la clôture, le système calcule les résultats. Un scrutin clos sans procès-verbal (tout juste fermé,
        // ou dépouillement précédent refusé) est dépouillé ; un refus (chaîne rompue, urne incohérente) est signalé
        // et le comité le voit sur la fiche du scrutin, où il peut relancer le dépouillement.
        $aDepouiller = Scrutin::query()
            ->where('statut', StatutScrutin::Clos->value)
            ->whereDoesntHave('procesVerbal')
            ->get();

        foreach ($aDepouiller as $scrutin) {
            try {
                $depouiller->handle($scrutin);
                $this->components->info("{$scrutin->libelle} : dépouillé.");
            } catch (ValidationException $exception) {
                Log::warning("Dépouillement automatique refusé pour {$scrutin->code} : ".$exception->getMessage());
                $this->components->warn("{$scrutin->libelle} : ".$exception->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
