<?php

namespace App\Http\Controllers\Agents;

use App\Actions\Agents\AffecterAgent;
use App\Actions\Agents\TerminerAffectation;
use App\Actions\Audit\Journaliser;
use App\Enums\Acces\CodeRole;
use App\Enums\Audit\ActionAuditee;
use App\Http\Controllers\Controller;
use App\Models\Affectation;
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

        // La liste et ses filtres sont dans le composant Livewire Agents\ListeAgents.
        return view('agents.index', ['restreint' => $perimetre !== null]);
    }

    /**
     * Formulaire de saisie : l'envoi passe par le composant Livewire Agents\FormulaireAgent.
     */
    public function create(): View
    {
        Gate::authorize('create', Agent::class);

        return view('agents.formulaire', ['agent' => new Agent]);
    }

    public function show(Request $request, Agent $agent): View
    {
        Gate::authorize('view', $agent);

        $affectation = $agent->affectationLe()?->load('structure.departement');

        return view('agents.show', [
            'agent' => $agent->load('compte.roles'),
            'affectation' => $affectation,
            'anciennete' => $affectation?->duree(),
            'roles' => $agent->compte?->roles->map(fn ($role): string => $role->code->libelle())->implode(', '),
            'ineligibilite' => Edition::courante()?->motifIneligibilite($agent->matricule),
            'peutModifier' => $request->user()->can('update', $agent),
            'structures' => $this->structures(null),
        ]);
    }

    public function edit(Agent $agent): View
    {
        Gate::authorize('update', $agent);

        return view('agents.formulaire', ['agent' => $agent]);
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
