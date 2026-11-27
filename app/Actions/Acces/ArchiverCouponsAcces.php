<?php

namespace App\Actions\Acces;

use App\Actions\Audit\Journaliser;
use App\Enums\Audit\ActionAuditee;
use App\Jobs\Acces\PreparerArchiveCoupons;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Archive ZIP des coupons individuels (mode « code imprimé ») : un dossier par structure d'affectation, un PDF par
 * personne. Un PDF prend environ un tiers de seconde : l'archive se prépare en file d'attente, par paquets, pour
 * tenir sous le délai d'une tâche. L'état (en cours, prête, en échec) est gardé en cache pour son auteur ; le
 * fichier, confidentiel, est supprimé dès qu'il est téléchargé ou qu'une nouvelle archive est demandée.
 * L'archive entière est tracée une fois au journal (R56), sans les codes.
 */
class ArchiverCouponsAcces
{
    public const PAQUET = 50;

    public const EN_COURS = 'en_cours';

    public const PRETE = 'prete';

    public const ECHEC = 'echec';

    public function __construct(private ImprimerCodesAcces $imprimer, private Journaliser $journal) {}

    /**
     * Lance la préparation, pour tous les comptes qui ont un code ou pour les seuls comptes choisis.
     *
     * @param  list<int>|null  $comptes
     * @return int Nombre de coupons à préparer (0 : aucun compte choisi n'a de code, rien n'est lancé).
     */
    public function lancer(User $auteur, ?array $comptes = null): int
    {
        $plan = $this->imprimer->planArchive($comptes);
        if ($plan === []) {
            return 0;
        }

        $this->supprimer($auteur->id);
        $jeton = (string) Str::uuid();
        $this->enregistrer($auteur->id, [
            'jeton' => $jeton,
            'etat' => self::EN_COURS,
            'fait' => 0,
            'total' => count($plan),
            'selection' => $comptes !== null,
        ]);

        PreparerArchiveCoupons::dispatch($jeton, $auteur->id, $plan);

        return count($plan);
    }

    /**
     * @return array{jeton: string, etat: string, fait: int, total: int, selection: bool}|null
     */
    public function etat(int $auteur): ?array
    {
        return Cache::get($this->cle($auteur));
    }

    /**
     * Prépare un paquet de coupons (tâche PreparerArchiveCoupons), puis assemble l'archive après le dernier.
     *
     * @param  list<array{compte: int, structure: string, chemin: string}>  $plan
     * @return int|null Position du paquet suivant, ou null quand l'archive est terminée ou abandonnée.
     */
    public function traiterPaquet(string $jeton, int $auteurId, array $plan, int $position): ?int
    {
        $etat = $this->etat($auteurId);
        if (($etat['jeton'] ?? null) !== $jeton) {
            $this->disque()->deleteDirectory($this->dossier($jeton));

            return null;
        }

        $paquet = array_slice($plan, $position, self::PAQUET);
        $comptes = User::whereKey(array_column($paquet, 'compte'))->where('actif', true)->whereNotNull('code_acces')->get()->keyBy('id');
        $auteur = User::find($auteurId);
        foreach ($paquet as $ligne) {
            $compte = $comptes->get($ligne['compte']);
            if ($compte !== null) {
                $this->disque()->put($this->dossier($jeton).'/'.$ligne['chemin'], $this->imprimer->couponIndividuel($compte, $ligne['structure'], $auteur));
            }
        }

        $suivante = $position + count($paquet);
        if ($suivante < count($plan)) {
            $this->enregistrer($auteurId, [...$etat, 'fait' => $suivante]);

            return $suivante;
        }

        $nombre = $this->assembler($jeton);
        $this->journal->handle(ActionAuditee::CodesAccesImprimes, null, ['lot' => $etat['selection'] ? 'archive sélection' : 'archive', 'nombre' => $nombre], $auteur);
        $this->enregistrer($auteurId, [...$etat, 'etat' => self::PRETE, 'fait' => $nombre, 'total' => $nombre]);

        return null;
    }

    /**
     * La tâche a échoué : l'écran le signale, les fichiers partiels sont supprimés.
     */
    public function echouer(string $jeton, int $auteurId): void
    {
        $this->disque()->deleteDirectory($this->dossier($jeton));
        $etat = $this->etat($auteurId);
        if (($etat['jeton'] ?? null) === $jeton) {
            $this->enregistrer($auteurId, [...$etat, 'etat' => self::ECHEC]);
        }
    }

    /**
     * Archive prête de l'auteur, à télécharger (chemin absolu et nom proposé), ou null.
     *
     * @return array{chemin: string, nom: string}|null
     */
    public function fichier(int $auteur): ?array
    {
        $etat = $this->etat($auteur);
        if (($etat['etat'] ?? null) !== self::PRETE || ! $this->disque()->exists($this->archive($etat['jeton']))) {
            return null;
        }

        return [
            'chemin' => $this->disque()->path($this->archive($etat['jeton'])),
            'nom' => $etat['selection'] ? 'coupons-selection-par-structure.zip' : 'coupons-par-structure.zip',
        ];
    }

    /**
     * Oublie l'archive de l'auteur et supprime ses fichiers (après le téléchargement, ou avant une nouvelle archive).
     */
    public function supprimer(int $auteur): void
    {
        $etat = $this->etat($auteur);
        if ($etat !== null) {
            $this->disque()->delete($this->archive($etat['jeton']));
            $this->disque()->deleteDirectory($this->dossier($etat['jeton']));
        }
        Cache::forget($this->cle($auteur));
    }

    /**
     * Oublie l'archive sans supprimer le fichier : le téléchargement en cours le supprime après l'envoi.
     */
    public function oublier(int $auteur): void
    {
        Cache::forget($this->cle($auteur));
    }

    private function assembler(string $jeton): int
    {
        $zip = new ZipArchive;
        if ($zip->open($this->disque()->path($this->archive($jeton)), ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Impossible de préparer l’archive des coupons.');
        }

        $racine = $this->dossier($jeton).'/';
        $fichiers = $this->disque()->allFiles($this->dossier($jeton));
        foreach ($fichiers as $fichier) {
            $zip->addFile($this->disque()->path($fichier), Str::after($fichier, $racine));
        }
        $zip->close();
        $this->disque()->deleteDirectory($this->dossier($jeton));

        return count($fichiers);
    }

    /**
     * @param  array{jeton: string, etat: string, fait: int, total: int, selection: bool}  $etat
     */
    private function enregistrer(int $auteur, array $etat): void
    {
        Cache::put($this->cle($auteur), $etat, now()->addDay());
    }

    private function cle(int $auteur): string
    {
        return "codes-acces:archive:{$auteur}";
    }

    private function dossier(string $jeton): string
    {
        return "coupons/{$jeton}";
    }

    private function archive(string $jeton): string
    {
        return "coupons/{$jeton}.zip";
    }

    private function disque(): Filesystem
    {
        return Storage::disk('local');
    }
}
