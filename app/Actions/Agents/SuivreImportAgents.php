<?php

namespace App\Actions\Agents;

use App\Jobs\Agents\ImporterFichierAgents;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Import des agents depuis l'écran (administrateur) : le fichier est déposé sur le serveur, puis importé en file
 * d'attente (ImporterFichierAgents) pour ne pas bloquer la page sur un long fichier. L'état de l'import (en cours,
 * terminé avec son bilan, en échec) est gardé en cache pour son auteur ; le fichier est supprimé après l'import.
 */
class SuivreImportAgents
{
    public const EN_COURS = 'en_cours';

    public const TERMINE = 'termine';

    public const ECHEC = 'echec';

    /**
     * Rejets gardés pour l'écran ; au-delà, seul leur nombre est affiché.
     */
    public const REJETS_AFFICHES = 200;

    /**
     * @return array{jeton: string, etat: string, fichier: string, lues: int, crees?: int, mis_a_jour?: int, affectations?: int, nombre_rejets?: int, rejets?: array<int, list<string>>, erreur?: string}|null
     */
    public function etat(int $auteur): ?array
    {
        return Cache::get($this->cle($auteur));
    }

    public function lancer(User $auteur, UploadedFile $fichier, ?string $dateEffet = null): void
    {
        $jeton = (string) Str::uuid();
        $extension = strtolower($fichier->getClientOriginalExtension()) === 'xlsx' ? 'xlsx' : 'csv';
        $chemin = $fichier->storeAs('imports/agents', "{$jeton}.{$extension}", 'local');

        $this->enregistrer($auteur->id, ['jeton' => $jeton, 'etat' => self::EN_COURS, 'fichier' => $fichier->getClientOriginalName(), 'lues' => 0]);

        ImporterFichierAgents::dispatch($jeton, $auteur->id, $chemin, $fichier->getClientOriginalName(), $dateEffet);
    }

    public function progresser(int $auteur, string $jeton, int $lues): void
    {
        $etat = $this->etat($auteur);
        if (($etat['jeton'] ?? null) === $jeton) {
            $this->enregistrer($auteur, [...$etat, 'lues' => $lues]);
        }
    }

    public function terminer(int $auteur, string $jeton, RapportImport $rapport): void
    {
        $etat = $this->etat($auteur) ?? ['jeton' => $jeton, 'fichier' => ''];

        $this->enregistrer($auteur, [
            ...$etat,
            'etat' => self::TERMINE,
            'lues' => $rapport->lignesAcceptees() + count($rapport->rejets),
            'crees' => $rapport->agentsCrees,
            'mis_a_jour' => $rapport->agentsMisAJour,
            'affectations' => $rapport->affectationsCreees,
            'nombre_rejets' => count($rapport->rejets),
            'rejets' => array_slice($rapport->rejets, 0, self::REJETS_AFFICHES, true),
        ]);
    }

    public function echouer(int $auteur, string $jeton, string $erreur): void
    {
        $etat = $this->etat($auteur) ?? ['jeton' => $jeton, 'fichier' => '', 'lues' => 0];
        $this->enregistrer($auteur, [...$etat, 'etat' => self::ECHEC, 'erreur' => $erreur]);
    }

    public function oublier(int $auteur): void
    {
        Cache::forget($this->cle($auteur));
    }

    public function supprimerFichier(string $chemin): void
    {
        Storage::disk('local')->delete($chemin);
    }

    /**
     * @param  array<string, mixed>  $etat
     */
    private function enregistrer(int $auteur, array $etat): void
    {
        Cache::put($this->cle($auteur), $etat, now()->addDay());
    }

    private function cle(int $auteur): string
    {
        return "agents:import:{$auteur}";
    }
}
