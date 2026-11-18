<?php

namespace App\Actions\Editions;

use App\Models\Edition;
use App\Models\Phase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Crée une édition avec ses trois phases (R16, R17), ou modifie une édition encore en préparation.
 * Le vote blanc et le quorum sont des paramètres de l'édition, à valider par le métier.
 */
class EnregistrerEdition
{
    /**
     * @param  array{annee: int, libelle: string, date_debut: string, date_fin: string, date_gel: string, vote_blanc_autorise?: bool, quorum?: float|int|string|null}  $donnees
     *
     * @throws ValidationException
     */
    public function handle(array $donnees, ?Edition $edition = null): Edition
    {
        if ($edition !== null && ! $edition->estModifiable()) {
            throw ValidationException::withMessages(['edition' => 'Une édition lancée ou clôturée ne peut plus être modifiée.']);
        }

        $donnees = Validator::make($donnees, [
            'annee' => ['required', 'integer', 'between:2000,2100', Rule::unique('editions', 'annee')->ignore($edition?->id)],
            'libelle' => ['required', 'string', 'max:160'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'date_gel' => ['required', 'date', 'before_or_equal:date_debut'],
            'vote_blanc_autorise' => ['sometimes', 'boolean'],
            'quorum' => ['nullable', 'numeric', 'gt:0', 'max:100'],
        ], [
            'annee.unique' => 'Une édition existe déjà pour cette année (R16).',
            'date_fin.after_or_equal' => 'La date de fin doit suivre la date de début.',
            'date_gel.before_or_equal' => 'Les listes électorales doivent être gelées au plus tard au début de l’édition.',
            'quorum.gt' => 'Le quorum est un pourcentage de participation compris entre 0 et 100.',
            'quorum.max' => 'Le quorum est un pourcentage de participation compris entre 0 et 100.',
            'required' => 'Le champ :attribute est obligatoire.',
        ])->validate();

        return DB::transaction(function () use ($donnees, $edition): Edition {
            $edition ??= new Edition;
            $estNouvelle = ! $edition->exists;
            $edition->fill($donnees)->save();

            // R17 : une édition comporte exactement trois phases ordonnées.
            if ($estNouvelle) {
                foreach (Phase::LIBELLES as $numero => $libelle) {
                    $edition->phases()->create(['numero' => $numero, 'libelle' => $libelle]);
                }
            }

            return $edition->load('phases');
        });
    }
}
