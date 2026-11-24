<?php

namespace App\Livewire\Agents;

use App\Actions\Agents\AffecterAgent;
use App\Actions\Agents\EnregistrerAgent;
use App\Actions\Audit\Journaliser;
use App\Enums\Agents\Sexe;
use App\Enums\Audit\ActionAuditee;
use App\Livewire\Concerns\SignaleLesErreurs;
use App\Models\Agent;
use App\Models\Structure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Saisie et modification d'un agent (module 03), en étapes, envoyées sans recharger la page. À la création,
 * l'agent est affecté dans la même transaction (R11) ; l'enregistrement est tracé au journal (R56).
 */
class FormulaireAgent extends Component
{
    use SignaleLesErreurs;

    /**
     * Champs texte de la fiche, dans l'ordre de la table agents.
     */
    private const CHAMPS = ['matricule', 'npi', 'nom', 'prenoms', 'sexe', 'date_naissance', 'date_prise_service', 'telephone', 'email', 'fonction', 'statut'];

    public ?Agent $agent = null;

    /**
     * @var array<string, string>
     */
    public array $fiche = [];

    public string $structureId = '';

    public string $dateDebut = '';

    public function mount(?Agent $agent = null): void
    {
        $this->agent = $agent?->exists ? $agent : null;
        $this->autoriser();

        foreach (self::CHAMPS as $champ) {
            $valeur = $this->agent?->{$champ};
            $this->fiche[$champ] = match (true) {
                $valeur instanceof Carbon => $valeur->format('Y-m-d'),
                $valeur instanceof Sexe => $valeur->value,
                default => (string) $valeur,
            };
        }
        $this->dateDebut = now()->toDateString();
    }

    /**
     * Contrôle des droits à chaque mise à jour du composant, envoi compris.
     */
    public function hydrate(): void
    {
        $this->autoriser();
    }

    /**
     * Structures d'affectation possibles (création seulement).
     *
     * @return Collection<int, Structure>
     */
    #[Computed]
    public function structures(): Collection
    {
        return Structure::actives()->orderBy('code')->get(['id', 'code', 'libelle']);
    }

    public function enregistrer(EnregistrerAgent $enregistrer, AffecterAgent $affecter, Journaliser $journal): mixed
    {
        $this->resetErrorBag();

        try {
            if ($this->agent === null) {
                ['structure' => $structure, 'date' => $date] = $this->affectation();
                $agent = DB::transaction(function () use ($enregistrer, $affecter, $journal, $structure, $date): Agent {
                    $agent = $enregistrer->handle($this->donnees());
                    $affecter->handle($agent, $structure, $date);
                    $journal->handle(ActionAuditee::AgentEnregistre, $agent, ['matricule' => $agent->matricule, 'creation' => true, 'structure' => $structure->code]);

                    return $agent;
                });
                session()->flash('succes', "L’agent {$agent->matricule} est enregistré et affecté à {$structure->code}.");
            } else {
                $agent = $this->agent;
                DB::transaction(function () use ($enregistrer, $journal, $agent): void {
                    $enregistrer->handle($this->donnees(), $agent);
                    $journal->handle(ActionAuditee::AgentEnregistre, $agent, ['matricule' => $agent->matricule, 'creation' => false, 'modifications' => array_keys($agent->getChanges())]);
                });
                session()->flash('succes', 'La fiche de l’agent est enregistrée.');
            }
        } catch (ValidationException $exception) {
            $this->signalerErreurs($exception);

            return null;
        }

        return $this->redirectRoute('agents.show', $agent, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.agents.formulaire-agent', ['sexes' => Sexe::cases()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function donnees(): array
    {
        $donnees = collect($this->fiche)
            ->only(self::CHAMPS)
            ->map(fn ($valeur): ?string => trim((string) $valeur) === '' ? null : trim((string) $valeur));

        return $donnees
            ->put('matricule', (string) $donnees->get('matricule'))
            ->put('nom', mb_strtoupper((string) $donnees->get('nom')))
            ->put('prenoms', (string) $donnees->get('prenoms'))
            ->put('sexe', (string) $donnees->get('sexe'))
            ->all();
    }

    /**
     * @return array{structure: Structure, date: Carbon}
     *
     * @throws ValidationException
     */
    private function affectation(): array
    {
        $donnees = Validator::make(['structure_id' => $this->structureId, 'date_debut' => $this->dateDebut], [
            'structure_id' => ['required', 'integer', 'exists:structures,id'],
            'date_debut' => ['required', 'date'],
        ], [
            'structure_id.required' => 'Choisissez la structure d’affectation.',
            'date_debut.required' => 'Indiquez la date de début de l’affectation.',
            'date' => 'Date invalide.',
        ])->validate();

        return ['structure' => Structure::findOrFail($donnees['structure_id']), 'date' => Carbon::parse($donnees['date_debut'])];
    }

    private function autoriser(): void
    {
        $this->agent === null ? Gate::authorize('create', Agent::class) : Gate::authorize('update', $this->agent);
    }
}
