<?php

namespace App\Actions\Scrutins;

use App\Actions\Audit\Journaliser;
use App\Enums\Audit\ActionAuditee;
use App\Models\Candidate;
use App\Models\Scrutin;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Ajoute, remplace ou retire la photo d'une candidate, affichée sur le bulletin, jusqu'à l'ouverture du scrutin
 * (comme la désactivation, R33). La photo arrive déjà recadrée au format de la carte du bulletin ; elle est rangée
 * sur le disque privé et servie aux seuls utilisateurs connectés. Chaque changement est tracé (R56).
 */
class ChangerPhotoCandidate
{
    /**
     * Dossier des photos sur le disque privé.
     */
    public const DOSSIER = 'photos-candidates';

    /**
     * Taille maximale d'une photo envoyée, en kilo-octets.
     */
    public const TAILLE_MAXIMALE = 5120;

    public function __construct(private Journaliser $journal) {}

    /**
     * Enregistre la photo, ou la retire quand $photo est null.
     *
     * @throws ValidationException
     */
    public function handle(Candidate $candidate, ?UploadedFile $photo, ?User $auteur = null): Candidate
    {
        if ($photo !== null) {
            Validator::make(['photo' => $photo], [
                'photo' => ['image', 'mimes:jpeg,png,webp', 'max:'.self::TAILLE_MAXIMALE, 'dimensions:min_width=200,min_height=200'],
            ], [
                'photo.image' => 'Le fichier doit être une photo.',
                'photo.mimes' => 'La photo doit être au format JPG, PNG ou WebP.',
                'photo.max' => 'La photo ne doit pas dépasser 5 Mo.',
                'photo.dimensions' => 'La photo est trop petite (200 pixels de côté au moins).',
            ])->validate();
        }

        $ancienne = null;
        $chemin = null;

        try {
            $candidate = DB::transaction(function () use ($candidate, $photo, $auteur, &$ancienne, &$chemin): Candidate {
                // Le scrutin est verrouillé : sa bascule à l'ouverture attend la fin du changement (R33).
                $scrutin = Scrutin::lockForUpdate()->findOrFail($candidate->scrutin_id);
                $candidate = Candidate::lockForUpdate()->findOrFail($candidate->id)->setRelation('scrutin', $scrutin);

                if (! $scrutin->accepteLesDesistements()) {
                    throw ValidationException::withMessages(['candidate' => 'Le scrutin est ouvert : la photo ne peut plus être modifiée.']);
                }

                if ($photo === null && $candidate->photo === null) {
                    throw ValidationException::withMessages(['candidate' => 'La candidate n’a pas de photo.']);
                }

                $ancienne = $candidate->photo;
                $chemin = $photo?->storeAs(self::DOSSIER, Str::uuid7().'.'.$photo->extension(), 'local');
                $candidate->update(['photo' => $chemin ?: null]);
                $this->journal->handle(ActionAuditee::PhotoCandidateModifiee, $candidate, [
                    'scrutin' => $scrutin->code,
                    'matricule' => $candidate->agent?->matricule,
                    'operation' => match (true) {
                        $photo === null => 'retrait',
                        $ancienne === null => 'ajout',
                        default => 'remplacement',
                    },
                ], $auteur);

                return $candidate;
            });
        } catch (\Throwable $exception) {
            // Rien n'est enregistré : le fichier tout juste rangé est supprimé.
            if ($chemin) {
                Storage::disk('local')->delete($chemin);
            }

            throw $exception;
        }

        // L'ancienne photo n'est supprimée que si elle n'est plus utilisée (une qualifiée peut la partager).
        if ($ancienne !== null && ! Candidate::where('photo', $ancienne)->exists()) {
            Storage::disk('local')->delete($ancienne);
        }

        return $candidate;
    }
}
