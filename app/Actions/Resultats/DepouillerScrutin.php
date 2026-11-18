<?php

namespace App\Actions\Resultats;

use App\Actions\Vote\EmpreinteBulletin;
use App\Enums\Scrutins\StatutScrutin;
use App\Models\Candidate;
use App\Models\ProcesVerbal;
use App\Models\Scrutin;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Dépouille un scrutin clos (R47 à R50) et établit son procès-verbal provisoire, non numéroté.
 *
 * - Vérifie l'intégrité de la chaîne des bulletins (R44) et sa cohérence avec l'émargement.
 * - Calcule les voix, le pourcentage des suffrages exprimés et le rang de chaque candidate active (rang à égalité partagé).
 * - Désigne les lauréates : les nb_qualifiees premières de la phase (R48, R52). Une égalité à la limite des places
 *   qualificatives n'est pas tranchée : elle est signalée pour départage par le comité (R49, modalité à préciser).
 * - Indique si le quorum éventuel est atteint (R50).
 *
 * Le dépouillement peut être relancé tant que les résultats ne sont pas validés.
 */
class DepouillerScrutin
{
    public function __construct(private EmpreinteBulletin $empreinte) {}

    /**
     * @throws ValidationException
     */
    public function handle(Scrutin $scrutin): ProcesVerbal
    {
        return DB::transaction(function () use ($scrutin): ProcesVerbal {
            $scrutin = Scrutin::with(['phase', 'procesVerbal'])->lockForUpdate()->findOrFail($scrutin->id);

            if ($scrutin->statut !== StatutScrutin::Clos) {
                $this->echouer('Seul un scrutin clos peut être dépouillé (R46).');
            }

            if (! $this->empreinte->chaineEstIntacte($scrutin->id)) {
                $this->echouer('La chaîne des empreintes des bulletins est rompue : dépouillement impossible, le comité doit être alerté (R44).');
            }

            $inscrits = $scrutin->inscriptions()->count();
            $votants = $scrutin->inscriptions()->where('a_vote', true)->count();
            $bulletins = $scrutin->bulletins()->count();

            if ($bulletins !== $votants) {
                $this->echouer("Incohérence entre l’émargement ({$votants} votants) et l’urne ({$bulletins} bulletins) : dépouillement impossible.");
            }

            $blancs = $scrutin->bulletins()->where('est_blanc', true)->count();
            $exprimes = $bulletins - $blancs;
            $voix = $scrutin->bulletins()->where('est_blanc', false)
                ->selectRaw('candidate_id, count(*) as total')->groupBy('candidate_id')->pluck('total', 'candidate_id');

            $egalite = $this->classer($scrutin, $voix, $exprimes);
            $taux = $inscrits === 0 ? 0.0 : round($votants * 100 / $inscrits, 2);

            return ProcesVerbal::updateOrCreate(['scrutin_id' => $scrutin->id], [
                'date_etablissement' => now(),
                'nb_inscrits' => $inscrits,
                'nb_votants' => $votants,
                'nb_blancs' => $blancs,
                'nb_exprimes' => $exprimes,
                'taux_participation' => $taux,
                'quorum_atteint' => $scrutin->quorum === null || $taux >= (float) $scrutin->quorum,
                'egalite_a_departager' => $egalite,
                'motif_departage' => null,
                'empreinte_chaine' => $this->empreinte->derniere($scrutin->id),
            ]);
        });
    }

    /**
     * Enregistre voix, pourcentage, rang et lauréates ; renvoie vrai si une égalité reste à départager (R49).
     *
     * @param  Collection<int, int>  $voix  candidate_id => nombre de voix
     */
    private function classer(Scrutin $scrutin, Collection $voix, int $exprimes): bool
    {
        $scrutin->candidates()->update(['nb_voix' => null, 'pourcentage' => null, 'rang' => null, 'est_laureate' => false]);

        $candidates = $scrutin->candidates()->actives()->get()
            ->map(fn (Candidate $candidate): Candidate => $candidate->setAttribute('nb_voix', (int) ($voix[$candidate->id] ?? 0)))
            ->sortByDesc('nb_voix')
            ->values();

        $places = $scrutin->phase->nb_qualifiees;
        $rangPrecedent = 0;
        $voixPrecedentes = null;

        foreach ($candidates as $position => $candidate) {
            $rang = $candidate->nb_voix === $voixPrecedentes ? $rangPrecedent : $position + 1;
            $candidate->fill([
                'rang' => $rang,
                'pourcentage' => $exprimes === 0 ? 0 : round($candidate->nb_voix * 100 / $exprimes, 2),
            ]);
            [$rangPrecedent, $voixPrecedentes] = [$rang, $candidate->nb_voix];
        }

        $dansLesPlaces = $candidates->filter(fn (Candidate $c): bool => $c->rang <= $places);
        $egalite = $dansLesPlaces->count() > $places;
        $rangEnBalance = $egalite ? $dansLesPlaces->max('rang') : null;

        foreach ($candidates as $candidate) {
            $candidate->est_laureate = $candidate->rang <= $places && $candidate->rang !== $rangEnBalance;
            $candidate->save();
        }

        return $egalite;
    }

    /**
     * @throws ValidationException
     */
    private function echouer(string $message): never
    {
        throw ValidationException::withMessages(['scrutin' => $message]);
    }
}
