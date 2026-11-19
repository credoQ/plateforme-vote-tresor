<?php

namespace App\Http\Controllers\Agents;

use App\Actions\Agents\AffecterAgent;
use App\Actions\Agents\EnregistrerAgent;
use App\Actions\Agents\TerminerAffectation;
use App\Actions\Audit\Journaliser;
use App\Enums\Acces\CodeRole;
use App\Enums\Agents\Sexe;
use App\Enums\Audit\ActionAuditee;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Edition;
use App\Models\Structure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Gestion des agents (module 03) : l'administrateur saisit, modifie et affecte ; le comité et l'observateur
 * consultent ; le point focal contrôle les seuls agents de sa structure (R4).
 */
class AgentController extends Controller
{
    public function __construct(private Journaliser $journal) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Agent::class);
        $utilisateur = $request->user();
        $perimetre = $utilisateur->aLeRole(CodeRole::Administrateur, CodeRole::Comite, CodeRole::Observateur) ? null : $utilisateur->perimetre();

        $recherche = trim((string) $request->query('recherche', ''));
        $structureId = $request->integer('structure') ?: null;
        $sexe = Sexe::tryFrom((string) $request->query('sexe', ''));
        $situation = (string) $request->query('situation', 'en-service');

        $agents = Agent::query()
            ->with('affectationEnCours.structure')
            ->when($perimetre !== null, fn ($q) => $q->affectesLe(Carbon::today(), $perimetre))
            ->when($structureId !== null, fn ($q) => $q->affectesLe(Carbon::today(), [$structureId]))
            ->when($recherche !== '', fn ($q) => $q->where(fn ($r) => $r
                ->whereLike('matricule', "%{$recherche}%")->orWhereLike('nom', "%{$recherche}%")
                ->orWhereLike('prenoms', "%{$recherche}%")->orWhereLike('npi', "%{$recherche}%")))
            ->when($sexe !== null, fn ($q) => $q->where('sexe', $sexe->value))
            ->when($situation === 'en-service', fn ($q) => $q->enServiceLe())
            ->when($situation === 'hors-service', fn ($q) => $q->whereDoesntHave('affectations', fn ($a) => $a->enCoursLe()))
            ->orderBy('nom')->orderBy('prenoms')
            ->paginate(50)
            ->withQueryString();

        return view('agents.index', [
            'agents' => $agents,
            'structures' => $this->structures($perimetre),
            'recherche' => $recherche,
            'structureId' => $structureId,
            'sexe' => $sexe,
            'situation' => $situation,
            'restreint' => $perimetre !== null,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Agent::class);

        return view('agents.formulaire', ['agent' => new Agent, 'structures' => $this->structures(null)]);
    }

    public function store(Request $request, EnregistrerAgent $enregistrer, AffecterAgent $affecter): RedirectResponse
    {
        Gate::authorize('create', Agent::class);
        ['structure' => $structure, 'date' => $date] = $this->affectation($request);

        $agent = DB::transaction(function () use ($request, $enregistrer, $affecter, $structure, $date): Agent {
            $agent = $enregistrer->handle($this->donnees($request));
            $affecter->handle($agent, $structure, $date);
            $this->journal->handle(ActionAuditee::AgentEnregistre, $agent, ['matricule' => $agent->matricule, 'creation' => true, 'structure' => $structure->code]);

            return $agent;
        });

        return redirect()->route('agents.show', $agent)->with('succes', "L’agent {$agent->matricule} est enregistré et affecté à {$structure->code}.");
    }

    public function show(Request $request, Agent $agent): View
    {
        Gate::authorize('view', $agent);

        $recherche = trim((string) $request->query('recherche', ''));
        $periode = (string) $request->query('periode', '');

        return view('agents.show', [
            'agent' => $agent->load('compte.roles'),
            'affectations' => $agent->affectations()
                ->with('structure')
                ->when($recherche !== '', fn ($q) => $q->whereHas('structure', fn ($s) => $s
                    ->whereLike('code', "%{$recherche}%")->orWhereLike('libelle', "%{$recherche}%")))
                ->when($periode === 'en-cours', fn ($q) => $q->whereNull('date_fin'))
                ->when($periode === 'terminees', fn ($q) => $q->whereNotNull('date_fin'))
                ->paginate(10)
                ->withQueryString(),
            'recherche' => $recherche,
            'periode' => $periode,
            'affectation' => $agent->affectationLe(),
            'ineligibilite' => Edition::courante()?->motifIneligibilite($agent->matricule),
            'peutModifier' => $request->user()->can('update', $agent),
            'structures' => $this->structures(null),
        ]);
    }

    public function edit(Agent $agent): View
    {
        Gate::authorize('update', $agent);

        return view('agents.formulaire', ['agent' => $agent, 'structures' => $this->structures(null)]);
    }

    public function update(Request $request, Agent $agent, EnregistrerAgent $enregistrer): RedirectResponse
    {
        Gate::authorize('update', $agent);

        DB::transaction(function () use ($request, $agent, $enregistrer): void {
            $enregistrer->handle($this->donnees($request), $agent);
            $this->journal->handle(ActionAuditee::AgentEnregistre, $agent, ['matricule' => $agent->matricule, 'creation' => false, 'modifications' => array_keys($agent->getChanges())]);
        });

        return redirect()->route('agents.show', $agent)->with('succes', 'La fiche de l’agent est enregistrée.');
    }

    /**
     * Mutation (R12) : l'affectation en cours est close la veille de la nouvelle.
     */
    public function affecter(Request $request, Agent $agent, AffecterAgent $affecter): RedirectResponse
    {
        Gate::authorize('update', $agent);
        ['structure' => $structure, 'date' => $date] = $this->affectation($request);

        DB::transaction(function () use ($agent, $affecter, $structure, $date): void {
            $affecter->handle($agent, $structure, $date);
            $this->journal->handle(ActionAuditee::AgentAffecte, $agent, ['matricule' => $agent->matricule, 'structure' => $structure->code, 'date_debut' => $date->toDateString()]);
        });

        return redirect()->route('agents.show', $agent)->with('succes', "L’agent est affecté à {$structure->code} à partir du {$date->format('d/m/Y')}.");
    }

    /**
     * Départ de l'agent (retraite, fin de détachement) : il n'est plus en service (R13).
     */
    public function terminer(Request $request, Agent $agent, TerminerAffectation $terminer): RedirectResponse
    {
        Gate::authorize('update', $agent);
        $donnees = $request->validate(['date_fin' => ['required', 'date']], ['required' => 'Indiquez la date de fin.', 'date' => 'Date invalide.']);
        $date = Carbon::parse($donnees['date_fin']);

        DB::transaction(function () use ($agent, $terminer, $date): void {
            $terminer->handle($agent, $date);
            $this->journal->handle(ActionAuditee::AffectationTerminee, $agent, ['matricule' => $agent->matricule, 'date_fin' => $date->toDateString()]);
        });

        return redirect()->route('agents.show', $agent)->with('succes', "L’affectation de l’agent prend fin le {$date->format('d/m/Y')}.");
    }

    /**
     * @return array<string, mixed>
     */
    private function donnees(Request $request): array
    {
        return collect($request->only(['matricule', 'npi', 'nom', 'prenoms', 'sexe', 'date_naissance', 'date_prise_service', 'telephone', 'email', 'fonction', 'statut']))
            ->map(fn ($valeur) => is_string($valeur) ? (trim($valeur) === '' ? null : trim($valeur)) : $valeur)
            ->put('matricule', trim((string) $request->input('matricule')))
            ->put('nom', mb_strtoupper(trim((string) $request->input('nom'))))
            ->put('prenoms', trim((string) $request->input('prenoms')))
            ->put('sexe', (string) $request->input('sexe'))
            ->all();
    }

    /**
     * @return array{structure: Structure, date: Carbon}
     *
     * @throws ValidationException
     */
    private function affectation(Request $request): array
    {
        $donnees = $request->validate([
            'structure_id' => ['required', 'integer', 'exists:structures,id'],
            'date_debut' => ['required', 'date'],
        ], [
            'structure_id.required' => 'Choisissez la structure d’affectation.',
            'date_debut.required' => 'Indiquez la date de début de l’affectation.',
            'date' => 'Date invalide.',
        ]);

        return ['structure' => Structure::findOrFail($donnees['structure_id']), 'date' => Carbon::parse($donnees['date_debut'])];
    }

    /**
     * @param  list<int>|null  $perimetre
     * @return Collection<int, Structure>
     */
    private function structures(?array $perimetre): Collection
    {
        return Structure::actives()
            ->when($perimetre !== null, fn ($q) => $q->whereKey($perimetre))
            ->orderBy('code')
            ->get(['id', 'code', 'libelle']);
    }
}
