<?php

namespace App\Actions\Editions;

use App\Enums\Editions\Decoupage;
use App\Enums\Referentiel\CodePoleElectoral;
use App\Enums\Scrutins\OrigineCandidate;
use App\Models\Edition;
use App\Models\Phase;
use App\Models\PoleElectoral;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Paramètre les phases d'une édition et leurs collèges (R17, R20 à R23, R28, R52).
 *
 * - Une à trois phases, numérotées dans l'ordre.
 * - Chaque phase a au moins un collège : le pôle départemental, le pôle structures, ou toute la DGTCP, seule.
 * - La qualification suppose une phase précédente qui couvre le même collège.
 *
 * Appelée dans la transaction de l'enregistrement de l'édition. Les dates déjà planifiées d'une phase conservée restent.
 */
class OrganiserPhases
{
    /**
     * @param  array<int|string, array{libelle?: string, colleges?: array<string, array{decoupage?: string, origine?: string, nb_elues?: int|string}>}>  $phases
     *
     * @throws ValidationException
     */
    public function handle(Edition $edition, array $phases): void
    {
        if ($edition->exists && ! $edition->organisationEstModifiable()) {
            throw ValidationException::withMessages(['phases' => 'Les phases ne se paramètrent plus une fois l’édition lancée ou ses scrutins générés.']);
        }

        $phases = $this->valider($phases);
        $poles = PoleElectoral::all()->keyBy(fn (PoleElectoral $pole): string => $pole->code->value);

        $edition->phases()->where('numero', '>', count($phases))->delete();

        foreach ($phases as $numero => $donnees) {
            $phase = $edition->phases()->firstOrNew(['numero' => $numero]);
            $phase->fill(['libelle' => $donnees['libelle']])->save();

            $phase->colleges()->delete();
            foreach ($donnees['colleges'] as $cle => $college) {
                $phase->colleges()->create([
                    'pole_electoral_id' => $cle === Phase::COLLEGE_DGTCP ? null : $poles[$cle]->id,
                    'decoupage' => $college['decoupage'],
                    'origine_candidates' => $college['origine'],
                    'nb_elues' => (int) $college['nb_elues'],
                ]);
            }
        }
    }

    /**
     * @param  array<int|string, mixed>  $phases
     * @return array<int, array{libelle: string, colleges: array<string, array{decoupage: string, origine: string, nb_elues: int|string}>}>
     *
     * @throws ValidationException
     */
    private function valider(array $phases): array
    {
        $cles = [...array_map(fn (CodePoleElectoral $pole): string => $pole->value, CodePoleElectoral::cases()), Phase::COLLEGE_DGTCP];

        $phases = Validator::make(['phases' => $phases], [
            'phases' => ['required', 'array', 'min:1', 'max:'.Phase::NOMBRE_MAXIMUM],
            'phases.*.libelle' => ['required', 'string', 'max:80'],
            'phases.*.colleges' => ['required', 'array', 'min:1'],
            'phases.*.colleges.*.decoupage' => ['required', Rule::enum(Decoupage::class)],
            'phases.*.colleges.*.origine' => ['required', Rule::enum(OrigineCandidate::class)],
            'phases.*.colleges.*.nb_elues' => ['required', 'integer', 'min:1', 'max:20'],
        ], [
            'phases.required' => 'Une édition compte au moins une phase (R17).',
            'phases.max' => 'Une édition compte au plus trois phases (R17).',
            'phases.*.libelle.required' => 'Chaque phase a un libellé.',
            'phases.*.colleges.required' => 'Chaque phase compte au moins un collège.',
            'phases.*.colleges.*.nb_elues.min' => 'Chaque scrutin élit au moins une lauréate (R52).',
            'phases.*.colleges.*.nb_elues.max' => 'Un scrutin élit au plus vingt lauréates.',
        ])->validate()['phases'];

        ksort($phases);
        if (array_keys($phases) !== range(1, count($phases))) {
            $this->echouer('Les phases se suivent sans trou, à partir de la phase 1 (R17).');
        }

        foreach ($phases as $numero => $phase) {
            $colleges = $phase['colleges'];

            if (array_diff(array_keys($colleges), $cles) !== []) {
                $this->echouer("Phase {$numero} : collège inconnu.");
            }

            if (isset($colleges[Phase::COLLEGE_DGTCP]) && count($colleges) > 1) {
                $this->echouer("Phase {$numero} : le collège de toute la DGTCP ne se combine avec aucun autre collège (R26).");
            }

            if (($colleges[Phase::COLLEGE_DGTCP]['decoupage'] ?? null) === Decoupage::ParCirconscription->value) {
                $this->echouer("Phase {$numero} : le collège de toute la DGTCP forme un seul scrutin.");
            }

            foreach ($colleges as $cle => $college) {
                if ($college['origine'] !== OrigineCandidate::Qualification->value) {
                    continue;
                }

                $precedents = array_keys($phases[$numero - 1]['colleges'] ?? []);
                if ($numero === 1) {
                    $this->echouer('Phase 1 : aucune lauréate n’est encore qualifiée ; choisissez des candidates d’office ou nominées (R35).');
                }

                if ($cle !== Phase::COLLEGE_DGTCP && ! in_array($cle, $precedents, true)) {
                    $this->echouer("Phase {$numero} : la qualification suppose que la phase précédente ait un collège pour le même pôle (R35).");
                }
            }
        }

        return $phases;
    }

    /**
     * @throws ValidationException
     */
    private function echouer(string $message): never
    {
        throw ValidationException::withMessages(['phases' => $message]);
    }
}
