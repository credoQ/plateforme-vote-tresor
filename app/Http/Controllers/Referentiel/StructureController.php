<?php

namespace App\Http\Controllers\Referentiel;

use App\Actions\Audit\Journaliser;
use App\Actions\Referentiel\ChangerStatutStructure;
use App\Actions\Referentiel\EnregistrerStructure;
use App\Enums\Audit\ActionAuditee;
use App\Http\Controllers\Controller;
use App\Models\Departement;
use App\Models\PoleElectoral;
use App\Models\Structure;
use App\Models\TypeStructure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Référentiel des structures (module 02) : l'administrateur crée, modifie, désactive et réactive les structures
 * (R5, R8, R9) ; le comité et l'observateur consultent.
 */
class StructureController extends Controller
{
    public function __construct(private Journaliser $journal) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Structure::class);

        // La liste et ses filtres sont dans le composant Livewire Referentiel\ListeStructures.
        return view('referentiel.structures.index');
    }

    /**
     * Types de structure classés par pôle électoral (R6, R7) et départements avec leurs postes déconcentrés (R9).
     */
    /**
     * Types de structure (filtrés par pôle et par recherche) et départements (filtrés par recherche) : chaque
     * tableau a ses propres paramètres, pour que filtrer l'un ne vide pas l'autre.
     */
    public function organisation(Request $request): View
    {
        Gate::authorize('viewAny', Structure::class);

        $filtres = [
            'type' => trim((string) $request->query('type', '')),
            'pole' => (string) $request->query('pole', ''),
            'departement' => trim((string) $request->query('departement', '')),
        ];
        $contient = fn (string $texte, string $terme): bool => $terme === '' || str_contains(mb_strtolower($texte), mb_strtolower($terme));

        $poles = PoleElectoral::with(['typesStructure' => fn ($q) => $q->withCount(['structures' => fn (Builder $s) => $s->where('actif', true)])->orderBy('libelle')])
            ->orderBy('libelle')->get();

        return view('referentiel.organisation', [
            'types' => $poles
                ->filter(fn (PoleElectoral $pole): bool => $filtres['pole'] === '' || $pole->code->value === $filtres['pole'])
                ->flatMap(fn (PoleElectoral $pole) => $pole->typesStructure->map(fn ($type): array => ['pole' => $pole, 'type' => $type]))
                ->filter(fn (array $ligne): bool => $contient($ligne['type']->code->libelle().' '.$ligne['type']->code->value, $filtres['type']))
                ->values(),
            'poles' => $poles,
            'departements' => Departement::query()
                ->with('tresorerieDepartementale')
                ->withCount('tresoreriesCommunales')
                ->orderBy('libelle')->get()
                ->filter(fn (Departement $departement): bool => $contient($departement->code.' '.$departement->libelle, $filtres['departement']))
                ->values(),
            'filtres' => $filtres,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Structure::class);

        $structure = new Structure(['structure_parente_id' => $request->integer('parente') ?: null]);
        if ($structure->structure_parente_id !== null) {
            $parente = Structure::find($structure->structure_parente_id);
            $structure->fill(['niveau' => Structure::NIVEAU_SERVICE, 'type_structure_id' => $parente?->type_structure_id, 'departement_id' => $parente?->departement_id]);
        }

        return view('referentiel.structures.formulaire', $this->donneesFormulaire($structure));
    }

    public function store(Request $request, EnregistrerStructure $enregistrer): RedirectResponse
    {
        Gate::authorize('create', Structure::class);

        $structure = DB::transaction(function () use ($request, $enregistrer): Structure {
            $structure = $enregistrer->handle($this->donnees($request));
            $this->journal->handle(ActionAuditee::StructureEnregistree, $structure, ['code' => $structure->code, 'creation' => true]);

            return $structure;
        });

        return redirect()->route('structures.show', $structure)->with('succes', "La structure {$structure->code} est enregistrée.");
    }

    public function show(Request $request, Structure $structure): View
    {
        Gate::authorize('view', $structure);

        $recherche = trim((string) $request->query('recherche', ''));
        $statut = (string) $request->query('statut', 'toutes');

        return view('referentiel.structures.show', [
            'structure' => $structure->load(['typeStructure.poleElectoral', 'departement', 'structureParente']),
            'structuresFilles' => $structure->structuresFilles()
                ->with('typeStructure')
                ->withCount(['affectations as agents_en_service_count' => fn (Builder $q) => $q->enCoursLe()])
                ->when($recherche !== '', fn ($q) => $q->where(fn ($r) => $r
                    ->whereLike('code', "%{$recherche}%")->orWhereLike('sigle', "%{$recherche}%")->orWhereLike('libelle', "%{$recherche}%")))
                ->when($statut === 'actives', fn ($q) => $q->where('actif', true))
                ->when($statut === 'desactivees', fn ($q) => $q->where('actif', false))
                ->orderBy('code')
                ->paginate(25)
                ->withQueryString(),
            'recherche' => $recherche,
            'statut' => $statut,
            'agentsEnService' => $structure->affectations()->enCoursLe()->count(),
        ]);
    }

    public function edit(Structure $structure): View
    {
        Gate::authorize('update', $structure);

        return view('referentiel.structures.formulaire', $this->donneesFormulaire($structure));
    }

    public function update(Request $request, Structure $structure, EnregistrerStructure $enregistrer): RedirectResponse
    {
        Gate::authorize('update', $structure);

        DB::transaction(function () use ($request, $structure, $enregistrer): void {
            $enregistrer->handle($this->donnees($request), $structure);
            $this->journal->handle(ActionAuditee::StructureEnregistree, $structure, ['code' => $structure->code, 'creation' => false, 'modifications' => array_keys($structure->getChanges())]);
        });

        return redirect()->route('structures.show', $structure)->with('succes', 'La structure est enregistrée.');
    }

    public function desactiver(Request $request, Structure $structure, ChangerStatutStructure $changer): RedirectResponse
    {
        Gate::authorize('update', $structure);
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']], ['max' => 'Le motif ne dépasse pas 500 caractères.']);

        $changer->desactiver($structure, $donnees['motif'] ?? null);

        return redirect()->route('structures.show', $structure)->with('succes', "La structure {$structure->code} est désactivée.");
    }

    public function reactiver(Structure $structure, ChangerStatutStructure $changer): RedirectResponse
    {
        Gate::authorize('update', $structure);

        $changer->reactiver($structure);

        return redirect()->route('structures.show', $structure)->with('succes', "La structure {$structure->code} est réactivée.");
    }

    /**
     * @return array<string, mixed>
     */
    private function donneesFormulaire(Structure $structure): array
    {
        return [
            'structure' => $structure,
            'types' => TypeStructure::with('poleElectoral')->orderBy('libelle')->get(),
            'departements' => Departement::orderBy('libelle')->get(),
            'parentes' => Structure::query()
                ->where('niveau', Structure::NIVEAU_STRUCTURE_ELECTORALE)
                ->where(fn (Builder $q) => $q->where('actif', true)->orWhere('id', $structure->structure_parente_id))
                ->when($structure->exists, fn (Builder $q) => $q->whereKeyNot($structure->id))
                ->orderBy('code')
                ->get(['id', 'code', 'libelle']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function donnees(Request $request): array
    {
        $entier = fn (string $champ): ?int => filled($request->input($champ)) ? (int) $request->input($champ) : null;

        return [
            'code' => mb_strtoupper(trim((string) $request->input('code'))),
            'sigle' => filled($request->input('sigle')) ? trim((string) $request->input('sigle')) : null,
            'libelle' => trim((string) $request->input('libelle')),
            'niveau' => $entier('niveau') ?? Structure::NIVEAU_STRUCTURE_ELECTORALE,
            'type_structure_id' => $entier('type_structure_id'),
            'departement_id' => $entier('departement_id'),
            'structure_parente_id' => $entier('structure_parente_id'),
        ];
    }
}
