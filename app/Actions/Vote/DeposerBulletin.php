<?php

namespace App\Actions\Vote;

use App\Enums\Scrutins\StatutScrutin;
use App\Models\Agent;
use App\Models\Bulletin;
use App\Models\Candidate;
use App\Models\Inscription;
use App\Models\Scrutin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Enregistre le vote d'un électeur (R39 à R45).
 *
 * Dans une seule transaction : l'émargement de l'inscription, verrouillée contre le double vote, et la création
 * d'un bulletin sans aucun lien avec l'électeur (R43). Les deux horodatages sont tronqués à l'heure et la date
 * de mise à jour de l'inscription n'est pas touchée : ni l'heure ni l'ordre des votes ne permettent de relier
 * un émargement à un bulletin de la chaîne.
 */
class DeposerBulletin
{
    public const CANAL_WEB = 'WEB';

    public function __construct(private EmpreinteBulletin $empreinte) {}

    /**
     * @param  Candidate|null  $choix  la candidate choisie, ou null pour un vote blanc
     *
     * @throws ValidationException
     */
    public function handle(Agent $electeur, Scrutin $scrutin, ?Candidate $choix, string $canal = self::CANAL_WEB): AccuseDeVote
    {
        return DB::transaction(function () use ($electeur, $scrutin, $choix, $canal): AccuseDeVote {
            // Verrou du scrutin : sérialise la chaîne des empreintes (R44).
            $scrutin = Scrutin::with('phase.edition')->lockForUpdate()->findOrFail($scrutin->id);
            $edition = $scrutin->phase->edition;

            // R39 : vote possible uniquement pendant l'ouverture du scrutin.
            $maintenant = now();
            if ($scrutin->statut !== StatutScrutin::Ouvert
                || $maintenant->lt($scrutin->date_heure_ouverture)
                || $maintenant->gte($scrutin->date_heure_fermeture)) {
                $this->echouer('scrutin', 'Le scrutin n’est pas ouvert : le vote n’est pas possible.');
            }

            // R40 : uniquement dans le scrutin où l'électeur est inscrit, une seule fois.
            $inscription = Inscription::where('scrutin_id', $scrutin->id)->where('agent_id', $electeur->id)->lockForUpdate()->first();
            if ($inscription === null) {
                $this->echouer('scrutin', 'Vous n’êtes pas inscrit sur la liste électorale de ce scrutin.');
            }

            if ($inscription->a_vote) {
                $this->echouer('scrutin', 'Vous avez déjà voté à ce scrutin : le vote est définitif.');
            }

            $this->verifierChoix($scrutin, $choix, $electeur, $edition->vote_blanc_autorise, $edition->vote_pour_soi_autorise);

            $heure = $maintenant->copy()->startOfHour();
            $numero = Str::upper(Str::random(12));

            $inscription->timestamps = false;
            $inscription->fill(['a_vote' => true, 'date_heure_vote' => $heure, 'canal' => $canal, 'numero_accuse' => $numero])->save();

            $bulletin = new Bulletin([
                'scrutin_id' => $scrutin->id,
                'candidate_id' => $choix?->id,
                'est_blanc' => $choix === null,
                'date_heure' => $heure,
                'empreinte_precedente' => $this->empreinte->derniere($scrutin->id),
            ]);
            $bulletin->id = $bulletin->newUniqueId();
            $bulletin->empreinte = $this->empreinte->calculer($bulletin);
            $bulletin->save();

            return new AccuseDeVote($numero, $scrutin->libelle, $heure->toImmutable());
        });
    }

    /**
     * R33, R41, R45 : une seule candidate active du scrutin, ou un vote blanc si l'édition l'autorise.
     *
     * @throws ValidationException
     */
    private function verifierChoix(Scrutin $scrutin, ?Candidate $choix, Agent $electeur, bool $blancAutorise, bool $votePourSoiAutorise): void
    {
        if ($choix === null) {
            if (! $blancAutorise) {
                $this->echouer('candidate_id', 'Le vote blanc n’est pas autorisé pour cette édition.');
            }

            return;
        }

        if ($choix->scrutin_id !== $scrutin->id || ! $choix->fresh()?->estActive()) {
            $this->echouer('candidate_id', 'Cette candidate ne figure pas sur la liste des candidates du scrutin.');
        }

        if (! $votePourSoiAutorise && $choix->agent_id === $electeur->id) {
            $this->echouer('candidate_id', 'Le vote pour soi-même n’est pas autorisé pour cette édition.');
        }
    }

    /**
     * @throws ValidationException
     */
    private function echouer(string $champ, string $message): never
    {
        throw ValidationException::withMessages([$champ => $message]);
    }
}
