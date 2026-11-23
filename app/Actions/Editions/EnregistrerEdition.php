<?php

namespace App\Actions\Editions;

use App\Models\Edition;
use App\Models\Phase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Crée une édition avec ses phases et leurs collèges (R16, R17), ou modifie une édition encore en préparation.
 * Sans paramétrage des phases, une nouvelle édition reçoit l'organisation par défaut en trois phases.
 * Le vote blanc et le quorum sont des paramètres de l'édition, à valider par le métier.
 */
class EnregistrerEdition
{
    public function __construct(private OrganiserPhases $organiser) {}

    /**
     * @param  array{annee: int, libelle: string, date_debut: string, date_fin: string, date_gel: string, vote_blanc_autorise?: bool, vote_pour_soi_autorise?: bool, quorum?: float|int|string|null, delai_reeligibilite?: int, phases?: array<int, array<string, mixed>>}  $donnees
     *
     * @throws ValidationException
     */
    public function handle(array $donnees, ?Edition $edition = null): Edition
    {
        $phases = $donnees['phases'] ?? null;

        if ($edition !== null && ! $edition->estModifiable()) {
            throw ValidationException::withMessages(['edition' => 'Une édition lancée ou clôturée ne peut plus être modifiée.']);
        }

        // Le calendrier d'une édition peut déborder sur l'année suivante, jamais commencer avant son année.
        $annee = is_numeric($donnees['annee'] ?? null) ? (int) $donnees['annee'] : null;
        $pasAvantLAnnee = $annee !== null && $annee >= 2000 && $annee <= 2100 ? ["after_or_equal:{$annee}-01-01"] : [];

        $donnees = Validator::make($donnees, [
            'annee' => ['required', 'integer', 'between:2000,2100', Rule::unique('editions', 'annee')->ignore($edition?->id)],
            'libelle' => ['required', 'string', 'max:160'],
            'date_debut' => ['required', 'date', ...$pasAvantLAnnee],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            // R14 : le gel fixe la liste électorale ; il peut tomber avant ou pendant l'édition, toujours avant la première phase.
            'date_gel' => ['required', 'date', 'before_or_equal:date_fin', ...$pasAvantLAnnee],
            'vote_blanc_autorise' => ['sometimes', 'boolean'],
            'vote_pour_soi_autorise' => ['sometimes', 'boolean'],
            'quorum' => ['nullable', 'numeric', 'gt:0', 'max:100'],
            'delai_reeligibilite' => ['sometimes', 'integer', 'min:0', 'max:20'],
        ], [
            'annee.unique' => 'Une édition existe déjà pour cette année.',
            'date_debut.after_or_equal' => "L’édition {$annee} ne peut pas commencer avant le 01/01/{$annee}.",
            'date_gel.after_or_equal' => "Les listes de l’édition {$annee} ne peuvent pas être gelées avant le 01/01/{$annee}.",
            'date_fin.after_or_equal' => 'La date de fin doit suivre la date de début.',
            'date_gel.before_or_equal' => 'Les listes électorales doivent être gelées au plus tard à la fin de l’édition.',
            'quorum.gt' => 'Le quorum est un pourcentage de participation compris entre 0 et 100.',
            'quorum.max' => 'Le quorum est un pourcentage de participation compris entre 0 et 100.',
            'delai_reeligibilite.integer' => 'Le délai de rééligibilité est un nombre d’années, de 0 à 20.',
            'delai_reeligibilite.min' => 'Le délai de rééligibilité est un nombre d’années, de 0 à 20.',
            'delai_reeligibilite.max' => 'Le délai de rééligibilité est un nombre d’années, de 0 à 20.',
            'required' => 'Le champ :attribute est obligatoire.',
        ])->validate();

        // Une phase déjà planifiée doit toujours commencer après le gel (ParametrerPhase le vérifie à la planification).
        $premierePhase = $edition?->phases()->whereNotNull('date_debut')->orderBy('date_debut')->first();
        if ($premierePhase !== null && $premierePhase->date_debut->toDateString() <= Carbon::parse($donnees['date_gel'])->toDateString()) {
            throw ValidationException::withMessages(['date_gel' => "Les listes doivent être gelées avant le début de la {$premierePhase->libelle}, le {$premierePhase->date_debut->format('d/m/Y')}."]);
        }

        return DB::transaction(function () use ($donnees, $edition, $phases): Edition {
            $edition ??= new Edition;
            $estNouvelle = ! $edition->exists;

            if (! $estNouvelle && $phases !== null) {
                $this->organiser->handle($edition, $phases);
            }

            $edition->fill($donnees)->save();

            // R17 : sans paramétrage, une nouvelle édition reçoit l'organisation par défaut en trois phases.
            if ($estNouvelle) {
                $this->organiser->handle($edition, $phases ?? self::organisationParDefaut());
            }

            return $edition->load('phases.colleges');
        });
    }

    /**
     * Organisation par défaut, au format attendu par OrganiserPhases.
     *
     * @return array<int, array{libelle: string, colleges: array<string, array{decoupage: string, origine: string, nb_elues: int}>}>
     */
    public static function organisationParDefaut(): array
    {
        return Phase::ORGANISATION_PAR_DEFAUT;
    }
}
