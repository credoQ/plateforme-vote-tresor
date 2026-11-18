<?php

namespace App\Http\Controllers\Editions;

use App\Actions\Editions\EnregistrerEdition;
use App\Actions\Editions\LancerEdition;
use App\Http\Controllers\Controller;
use App\Models\Edition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Écrans du comité : création, paramétrage et lancement des éditions (R16, R17).
 */
class EditionController extends Controller
{
    public function index(): View
    {
        return view('editions.index', [
            'editions' => Edition::withCount('scrutins')->orderByDesc('annee')->get(),
        ]);
    }

    public function create(): View
    {
        return view('editions.formulaire', ['edition' => new Edition(['annee' => now()->year])]);
    }

    public function store(Request $request, EnregistrerEdition $enregistrer): RedirectResponse
    {
        $edition = $enregistrer->handle($this->donnees($request));

        return redirect()->route('editions.show', $edition)->with('succes', "L’édition {$edition->annee} est créée avec ses trois phases.");
    }

    public function show(Edition $edition): View
    {
        $edition->load(['phases.scrutins' => fn ($q) => $q->withCount([
            'inscriptions',
            'candidates as candidates_actives_count' => fn ($c) => $c->actives(),
        ])->orderBy('code')]);

        return view('editions.show', ['edition' => $edition]);
    }

    public function edit(Edition $edition): View
    {
        return view('editions.formulaire', ['edition' => $edition]);
    }

    public function update(Request $request, Edition $edition, EnregistrerEdition $enregistrer): RedirectResponse
    {
        $enregistrer->handle($this->donnees($request), $edition);

        return redirect()->route('editions.show', $edition)->with('succes', 'L’édition est enregistrée.');
    }

    public function lancer(Edition $edition, LancerEdition $lancer): RedirectResponse
    {
        $lancer->handle($edition);

        return redirect()->route('editions.show', $edition)->with('succes', "L’édition {$edition->annee} est en cours.");
    }

    /**
     * @return array<string, mixed>
     */
    private function donnees(Request $request): array
    {
        return [
            'annee' => $request->integer('annee'),
            'libelle' => $request->input('libelle'),
            'date_debut' => $request->input('date_debut'),
            'date_fin' => $request->input('date_fin'),
            'date_gel' => $request->input('date_gel'),
            'vote_blanc_autorise' => $request->boolean('vote_blanc_autorise'),
            'vote_pour_soi_autorise' => $request->boolean('vote_pour_soi_autorise'),
            'quorum' => $request->filled('quorum') ? $request->input('quorum') : null,
        ];
    }
}
