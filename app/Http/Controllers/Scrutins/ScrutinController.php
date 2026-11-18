<?php

namespace App\Http\Controllers\Scrutins;

use App\Actions\Scrutins\ArreterListes;
use App\Actions\Scrutins\ConstituerListes;
use App\Actions\Scrutins\GenererScrutins;
use App\Actions\Scrutins\PlanifierScrutin;
use App\Enums\Acces\CodeRole;
use App\Http\Controllers\Controller;
use App\Models\Phase;
use App\Models\Scrutin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Écrans des scrutins (module 05) : génération, planification, contrôle et arrêt des listes, candidates.
 * Le point focal consulte les listes et candidates de sa seule structure (R4).
 */
class ScrutinController extends Controller
{
    public const FUSEAU = 'Africa/Porto-Novo';

    public function generer(Phase $phase, GenererScrutins $generer): RedirectResponse
    {
        $rapport = $generer->handle($phase);

        return redirect()->to(route('editions.show', $phase->edition_id).'#phase-'.$phase->numero)
            ->with('succes', "{$rapport->scrutins->count()} scrutin(s) générés pour la {$phase->libelle}.")
            ->with('sans_femme', $rapport->circonscriptionsSansFemme);
    }

    public function show(Request $request, Scrutin $scrutin): View
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur->can('observer') || $utilisateur->can('controler-listes'), 403);
        $perimetre = $utilisateur->aLeRole(CodeRole::Comite, CodeRole::Observateur, CodeRole::Administrateur) ? null : $utilisateur->perimetre();
        $recherche = trim((string) $request->query('recherche', ''));

        $scrutin->load(['phase.edition', 'poleElectoral', 'structures' => fn ($q) => $q->orderBy('code'), 'procesVerbal']);

        $structuresInscription = $scrutin->inscriptions()->pluck('structure_id', 'agent_id');
        $candidates = $scrutin->candidates()->with(['agent', 'desactiveePar'])->get()
            ->each(fn ($c) => $c->setAttribute('structure_inscription_id', $structuresInscription[$c->agent_id] ?? null))
            ->filter(fn ($c) => $perimetre === null || in_array($c->structure_inscription_id, $perimetre, true))
            ->sortBy([['rang', 'asc'], [fn ($c) => $c->agent->nomComplet(), 'asc']])
            ->values();

        $inscriptions = $scrutin->inscriptions()
            ->with(['agent', 'structure'])
            ->when($perimetre !== null, fn ($q) => $q->whereIn('inscriptions.structure_id', $perimetre))
            ->when($recherche !== '', fn ($q) => $q->whereHas('agent', fn ($a) => $a
                ->whereLike('matricule', "%{$recherche}%")->orWhereLike('nom', "%{$recherche}%")->orWhereLike('prenoms', "%{$recherche}%")))
            ->join('agents', 'agents.id', '=', 'inscriptions.agent_id')
            ->orderBy('agents.nom')->orderBy('agents.prenoms')
            ->select('inscriptions.*')
            ->paginate(50)
            ->withQueryString();

        return view('scrutins.show', [
            'scrutin' => $scrutin,
            'candidates' => $candidates,
            'inscriptions' => $inscriptions,
            'recherche' => $recherche,
            'restreint' => $perimetre !== null,
            'fuseau' => self::FUSEAU,
        ]);
    }

    public function planifier(Request $request, Scrutin $scrutin, PlanifierScrutin $planifier): RedirectResponse
    {
        $donnees = $request->validate([
            'ouverture' => ['required', 'date_format:Y-m-d\TH:i'],
            'fermeture' => ['required', 'date_format:Y-m-d\TH:i'],
        ], ['required' => 'Indiquez la date et l’heure.', 'date_format' => 'Date et heure invalides.']);

        $planifier->handle(
            $scrutin,
            Carbon::createFromFormat('Y-m-d\TH:i', $donnees['ouverture'], self::FUSEAU)->utc(),
            Carbon::createFromFormat('Y-m-d\TH:i', $donnees['fermeture'], self::FUSEAU)->utc(),
        );

        return redirect()->route('scrutins.show', $scrutin)->with('succes', 'Le scrutin est planifié.');
    }

    public function actualiser(Scrutin $scrutin, ConstituerListes $constituer): RedirectResponse
    {
        $scrutin = $constituer->handle($scrutin);

        return redirect()->route('scrutins.show', $scrutin)
            ->with('succes', "Listes actualisées : {$scrutin->inscriptions_count} inscrit(s), {$scrutin->candidates_count} candidate(s).");
    }

    public function arreter(Scrutin $scrutin, ArreterListes $arreter): RedirectResponse
    {
        try {
            $arreter->handle($scrutin);
        } catch (ValidationException $exception) {
            return redirect()->route('scrutins.show', $scrutin)->withErrors($exception->errors());
        }

        return redirect()->route('scrutins.show', $scrutin)->with('succes', 'Les listes sont arrêtées : elles ne seront plus modifiées.');
    }
}
