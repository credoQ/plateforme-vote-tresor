<?php

namespace App\Livewire\Editions;

use App\Actions\Editions\EnregistrerEdition;
use App\Enums\Editions\Decoupage;
use App\Enums\Referentiel\CodePoleElectoral;
use App\Enums\Scrutins\OrigineCandidate;
use App\Livewire\Concerns\SignaleLesErreurs;
use App\Models\Edition;
use App\Models\Phase;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Création et paramétrage d'une édition, phases et collèges compris tant qu'ils sont modifiables (R16, R17),
 * en étapes envoyées sans recharger la page. La validation reste dans l'action EnregistrerEdition.
 */
class FormulaireEdition extends Component
{
    use SignaleLesErreurs;

    /**
     * Valeur du formulaire pour un collège sans scrutin dans la phase.
     */
    private const AUCUN_SCRUTIN = 'AUCUN';

    public ?Edition $edition = null;

    /**
     * Saisie, au format attendu par EnregistrerEdition (phases : numéro → libellé et collèges).
     *
     * @var array<string, mixed>
     */
    public array $form = [];

    /**
     * Phases retenues, ajoutées une à une par le bouton « Ajouter une phase » (composant Alpine organisationPhases).
     */
    public int $nombrePhases = 1;

    /**
     * Contrôle des droits à chaque requête du composant, envoi compris.
     */
    public function boot(): void
    {
        Gate::authorize('piloter');
    }

    public function mount(?Edition $edition = null): void
    {
        $this->edition = $edition?->exists ? $edition->load('phases.colleges.poleElectoral') : null;
        $edition = $this->edition ?? new Edition(['annee' => now()->year]);

        $this->form = [
            'annee' => (string) $edition->annee,
            'libelle' => (string) $edition->libelle,
            'date_debut' => (string) $edition->date_debut?->format('Y-m-d'),
            'date_fin' => (string) $edition->date_fin?->format('Y-m-d'),
            'vote_blanc_autorise' => (bool) $edition->vote_blanc_autorise,
            'vote_pour_soi_autorise' => $this->edition !== null ? (bool) $edition->vote_pour_soi_autorise : true,
            'quorum' => (string) $edition->quorum,
            'delai_reeligibilite' => (string) $edition->delai_reeligibilite,
            'phases' => $this->organisation(),
        ];
        $this->nombrePhases = $this->edition !== null
            ? max(1, count(array_filter($this->form['phases'], fn (array $phase): bool => $phase['active'])))
            : 1;
    }

    public function enregistrer(EnregistrerEdition $enregistrer): mixed
    {
        $this->resetErrorBag();
        $creation = $this->edition === null;

        try {
            $edition = $enregistrer->handle($this->donnees(), $this->edition);
        } catch (ValidationException $exception) {
            $this->signalerErreurs($exception);

            return null;
        }

        session()->flash('succes', $creation ? "L’édition {$edition->annee} est créée avec {$edition->phases()->count()} phase(s)." : 'L’édition est enregistrée.');

        return $this->redirectRoute('editions.show', $edition, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.editions.formulaire-edition', [
            'organisationModifiable' => $this->organisationModifiable(),
            'colleges' => [
                'DEPARTEMENTAL' => CodePoleElectoral::Departemental->libelle(),
                'STRUCTURES' => CodePoleElectoral::Structures->libelle(),
                Phase::COLLEGE_DGTCP => 'Ensemble de la DGTCP',
            ],
            'iconesColleges' => ['DEPARTEMENTAL' => 'map-pin', 'STRUCTURES' => 'building-2', Phase::COLLEGE_DGTCP => 'landmark'],
            'decoupages' => collect(Decoupage::cases())->mapWithKeys(fn (Decoupage $d): array => [$d->value => $d->libelle()])->all(),
            'origines' => collect(OrigineCandidate::cases())->mapWithKeys(fn (OrigineCandidate $o): array => [$o->value => $o->libelleCollege()])->all(),
        ]);
    }

    /**
     * La date de fin est ici un champ de l'édition : son erreur s'affiche sous le champ, pas en fenêtre.
     *
     * @return list<string>
     */
    protected function clesEnFenetre(): array
    {
        return ['edition', 'phase', 'scrutin', 'structure'];
    }

    private function organisationModifiable(): bool
    {
        return $this->edition === null || $this->edition->organisationEstModifiable();
    }

    /**
     * Les trois phases possibles, préremplies avec celles de l'édition, ou l'organisation par défaut pour une nouvelle édition.
     *
     * @return array<int, array{active: bool, libelle: string, colleges: array<string, array{decoupage: string, origine: string, nb_elues: int}>}>
     */
    private function organisation(): array
    {
        $base = $this->edition !== null
            ? $this->edition->phases->mapWithKeys(fn (Phase $phase): array => [$phase->numero => [
                'libelle' => $phase->libelle,
                'colleges' => $phase->colleges->mapWithKeys(fn ($college): array => [
                    $college->poleElectoral?->code->value ?? Phase::COLLEGE_DGTCP => ['decoupage' => $college->decoupage->value, 'origine' => $college->origine_candidates->value, 'nb_elues' => $college->nb_elues],
                ])->all(),
            ]])->all()
            : Phase::ORGANISATION_PAR_DEFAUT;

        $organisation = [];
        foreach (range(1, Phase::NOMBRE_MAXIMUM) as $numero) {
            $colleges = [];
            foreach (['DEPARTEMENTAL', 'STRUCTURES', Phase::COLLEGE_DGTCP] as $cle) {
                $colleges[$cle] = $base[$numero]['colleges'][$cle] ?? [
                    'decoupage' => self::AUCUN_SCRUTIN,
                    'origine' => $numero === 1 ? OrigineCandidate::Automatique->value : OrigineCandidate::Qualification->value,
                    'nb_elues' => 1,
                ];
            }

            $organisation[$numero] = ['active' => isset($base[$numero]), 'libelle' => $base[$numero]['libelle'] ?? "Phase {$numero}", 'colleges' => $colleges];
        }

        return $organisation;
    }

    /**
     * @return array<string, mixed>
     */
    private function donnees(): array
    {
        $valeur = fn (string $champ): ?string => trim((string) ($this->form[$champ] ?? '')) !== '' ? trim((string) $this->form[$champ]) : null;
        $donnees = [
            'annee' => (int) $valeur('annee'),
            'libelle' => $valeur('libelle'),
            'date_debut' => $valeur('date_debut'),
            'date_fin' => $valeur('date_fin'),
            'vote_blanc_autorise' => (bool) ($this->form['vote_blanc_autorise'] ?? false),
            'vote_pour_soi_autorise' => (bool) ($this->form['vote_pour_soi_autorise'] ?? false),
            'quorum' => $valeur('quorum'),
            'delai_reeligibilite' => $valeur('delai_reeligibilite') ?? 3,
        ];

        if (! $this->organisationModifiable()) {
            return $donnees;
        }

        // Phases retenues et collèges qui ont des scrutins, au format attendu par OrganiserPhases (R17).
        $phases = [];
        foreach ((array) ($this->form['phases'] ?? []) as $numero => $phase) {
            if ((int) $numero > $this->nombrePhases) {
                continue;
            }

            $phases[(int) $numero] = [
                'libelle' => $phase['libelle'] ?? null,
                'colleges' => collect($phase['colleges'] ?? [])->reject(fn ($college): bool => ($college['decoupage'] ?? self::AUCUN_SCRUTIN) === self::AUCUN_SCRUTIN)->all(),
            ];
        }

        return ['phases' => $phases] + $donnees;
    }
}
