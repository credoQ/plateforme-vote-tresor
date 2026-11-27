<?php

namespace App\Jobs\Agents;

use App\Actions\Agents\ImporterAgents;
use App\Actions\Agents\SuivreImportAgents;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Importe en file d'attente un fichier d'agents déposé depuis l'écran : agents, comptes électeurs et affectations
 * (ImporterAgents). Un fichier illisible ou sans colonne obligatoire est signalé à l'écran, sans nouvelle tentative.
 */
class ImporterFichierAgents implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * Sous le délai de reprise de la file (90 s) : environ 1 000 lignes en 15 s mesurées.
     */
    public int $timeout = 85;

    public function __construct(public string $jeton, public int $auteurId, public string $chemin, public string $nomFichier, public ?string $dateEffet = null) {}

    public function handle(ImporterAgents $importer, SuivreImportAgents $suivi): void
    {
        try {
            $rapport = $importer->handle(
                Storage::disk('local')->path($this->chemin),
                $this->dateEffet !== null ? Carbon::parse($this->dateEffet) : null,
                User::find($this->auteurId),
                $this->nomFichier,
                fn (int $lues) => $suivi->progresser($this->auteurId, $this->jeton, $lues),
            );
            $suivi->terminer($this->auteurId, $this->jeton, $rapport);
        } catch (InvalidArgumentException $erreur) {
            $suivi->echouer($this->auteurId, $this->jeton, $erreur->getMessage());
        } finally {
            $suivi->supprimerFichier($this->chemin);
        }
    }

    public function failed(?Throwable $erreur): void
    {
        $suivi = app(SuivreImportAgents::class);
        $suivi->echouer($this->auteurId, $this->jeton, 'L’import s’est interrompu. Les lignes déjà lues sont enregistrées ; relancez le même fichier pour terminer.');
        $suivi->supprimerFichier($this->chemin);
    }
}
