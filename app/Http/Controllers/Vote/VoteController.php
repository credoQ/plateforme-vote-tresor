<?php

namespace App\Http\Controllers\Vote;

use App\Actions\Vote\DeposerBulletin;
use App\Actions\Vote\EnvoyerAccuseDeVote;
use App\Actions\Vote\GenererAccuseDeVote;
use App\Enums\Editions\StatutPhase;
use App\Enums\Scrutins\StatutScrutin;
use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Inscription;
use App\Models\Scrutin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Vote de l'électeur (module 06) : son seul scrutin de la phase ouverte, le bulletin, la confirmation et l'accusé.
 * Le choix ne transite que dans la requête de vote : il n'est ni journalisé ni conservé en session (R43, R57).
 */
class VoteController extends Controller
{
    public const BLANC = 'blanc';

    public function show(Request $request): View
    {
        $inscription = $this->inscription($request);

        if ($inscription === null || $inscription->a_vote || ! $this->estOuvert($inscription->scrutin)) {
            return view('vote.etat', ['inscription' => $inscription, 'scrutin' => $inscription?->scrutin]);
        }

        $candidates = $this->candidates($inscription->scrutin);

        return view('vote.bulletin', [
            'inscription' => $inscription,
            'scrutin' => $inscription->scrutin,
            // Toutes les candidates : la recherche et le choix d'une structure filtrent dans la page (filtre-bulletin.js).
            'candidates' => $candidates,
            'total' => $candidates->count(),
            'structures' => $candidates->pluck('structure', 'structure_id')->sort(),
            'blancAutorise' => $inscription->scrutin->phase->edition->vote_blanc_autorise,
        ]);
    }

    public function store(Request $request, DeposerBulletin $deposer, EnvoyerAccuseDeVote $envoyer): RedirectResponse
    {
        $inscription = $this->inscription($request);
        if ($inscription === null) {
            return redirect()->route('vote');
        }

        $choix = $this->choix($request, $inscription->scrutin);
        $candidate = $choix === null ? null : Candidate::findOrFail($choix['id']);

        try {
            $deposer->handle($request->user()->agent, $inscription->scrutin, $candidate);
        } catch (ValidationException $exception) {
            return redirect()->route('vote')->withErrors($exception->errors());
        }

        // Le vote est enregistré (transaction validée) : l'accusé part par e-mail, en file d'attente.
        $envoye = $envoyer->handle($request->user(), $inscription);

        return redirect()->route('vote')->with('vote_enregistre', true)->with('accuse_envoye', $envoye);
    }

    /**
     * Accusé de vote en PDF, réservé à l'électeur qui a voté sous ce numéro.
     */
    public function accuse(Request $request, string $numero, GenererAccuseDeVote $generer): Response
    {
        $inscription = Inscription::query()
            ->where('numero_accuse', $numero)
            ->where('agent_id', $request->user()->agent_id)
            ->where('a_vote', true)
            ->firstOrFail();

        return response($generer->handle($inscription), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$generer->nomDeFichier($inscription).'"',
        ]);
    }

    private function inscription(Request $request): ?Inscription
    {
        return Inscription::query()
            ->with('scrutin.phase.edition')
            ->where('agent_id', $request->user()->agent_id)
            ->whereHas('scrutin.phase', fn ($phase) => $phase->where('statut', StatutPhase::Ouverte->value))
            ->first();
    }

    private function estOuvert(Scrutin $scrutin): bool
    {
        return $scrutin->statut === StatutScrutin::Ouvert
            && $scrutin->date_heure_ouverture?->isPast()
            && $scrutin->date_heure_fermeture?->isFuture();
    }

    /**
     * Candidates actives du scrutin, avec leur structure d'inscription (R33).
     *
     * @return Collection<int, array{id: int, nom: string, initiales: string, photo: string|null, avatar: array{tenue: int, peau: int, cheveux: int, coiffure: int}, structure: string, structure_id: int|null}>
     */
    private function candidates(Scrutin $scrutin): Collection
    {
        $structures = Inscription::where('scrutin_id', $scrutin->id)->with('structure:id,code,libelle')->get()->keyBy('agent_id');

        return $scrutin->candidates()->actives()->with('agent')->get()
            ->map(function (Candidate $candidate) use ($structures): array {
                $structure = $structures[$candidate->agent_id]?->structure ?? $candidate->agent->affectationLe()?->structure;

                return [
                    'id' => $candidate->id,
                    'nom' => $candidate->agent->nomComplet(),
                    'initiales' => mb_substr($candidate->agent->nom, 0, 1).mb_substr($candidate->agent->prenoms, 0, 1),
                    'photo' => $candidate->urlPhoto(),
                    'avatar' => $candidate->agent->avatar(),
                    'structure' => $structure?->libelle ?? '',
                    'structure_id' => $structure?->id,
                ];
            })
            ->sortBy('nom', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Choix transmis par le bulletin : une candidate active du scrutin, ou null pour un vote blanc.
     *
     * @return array{id: int, nom: string, initiales: string, photo: string|null, avatar: array{tenue: int, peau: int, cheveux: int, coiffure: int}, structure: string, structure_id: int|null}|null
     *
     * @throws ValidationException
     */
    private function choix(Request $request, Scrutin $scrutin): ?array
    {
        $valeur = (string) $request->input('candidate', '');

        if ($valeur === self::BLANC && $scrutin->phase->edition->vote_blanc_autorise) {
            return null;
        }

        $choix = $this->candidates($scrutin)->firstWhere('id', (int) $valeur);
        if ($choix === null) {
            throw ValidationException::withMessages(['candidate' => 'Choisissez une candidate du bulletin.'])->redirectTo(route('vote'));
        }

        return $choix;
    }
}
