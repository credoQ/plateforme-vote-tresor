<?php

namespace App\Actions\Editions;

use App\Enums\Editions\StatutPhase;
use App\Models\Phase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Planifie une phase (dates de début et de fin) et fixe son nombre de qualifiées par scrutin (R52).
 *
 * Les phases se suivent sans se chevaucher, après le gel des listes et dans le calendrier de l'édition (R17).
 */
class ParametrerPhase
{
    /**
     * @param  array{date_debut: string, date_fin: string}  $donnees
     *
     * @throws ValidationException
     */
    public function handle(Phase $phase, array $donnees): Phase
    {
        if ($phase->statut !== StatutPhase::Planifiee) {
            $this->echouer('phase', 'Une phase ouverte ou clôturée ne peut plus être replanifiée.');
        }

        $donnees = Validator::make($donnees, [
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
        ], [
            'date_fin.after_or_equal' => 'La phase doit se terminer après son début.',
            'required' => 'Le champ :attribute est obligatoire.',
        ])->validate();

        $edition = $phase->edition;
        $debut = Carbon::parse($donnees['date_debut'])->toDateString();
        $fin = Carbon::parse($donnees['date_fin'])->toDateString();

        if ($debut < $edition->date_debut->toDateString() || $fin > $edition->date_fin->toDateString()) {
            $this->echouer('date_debut', "La phase doit se dérouler pendant l’édition, du {$edition->date_debut->format('d/m/Y')} au {$edition->date_fin->format('d/m/Y')}.");
        }

        if ($debut <= $edition->date_gel->toDateString()) {
            $this->echouer('date_debut', 'Une phase ne peut commencer qu’après le gel des listes électorales.');
        }

        $voisines = $edition->phases()->whereKeyNot($phase->id)->get();
        $precedente = $voisines->firstWhere('numero', $phase->numero - 1);
        $suivante = $voisines->firstWhere('numero', $phase->numero + 1);

        // R17 : les phases sont ordonnées et ne se chevauchent pas.
        if ($precedente?->date_fin !== null && $debut <= $precedente->date_fin->toDateString()) {
            $this->echouer('date_debut', "La phase doit commencer après la fin de la phase {$precedente->numero}.");
        }

        if ($suivante?->date_debut !== null && $fin >= $suivante->date_debut->toDateString()) {
            $this->echouer('date_fin', "La phase doit se terminer avant le début de la phase {$suivante->numero}.");
        }

        DB::transaction(function () use ($phase, $donnees): void {
            $verrouillee = Phase::lockForUpdate()->findOrFail($phase->id);
            if ($verrouillee->statut !== StatutPhase::Planifiee) {
                $this->echouer('phase', 'Une phase ouverte ou clôturée ne peut plus être replanifiée.');
            }

            $verrouillee->update($donnees);
        });
        $phase->refresh();

        return $phase;
    }

    /**
     * @throws ValidationException
     */
    private function echouer(string $champ, string $message): never
    {
        throw ValidationException::withMessages([$champ => $message]);
    }
}
