<?php

namespace App\Livewire\Resultats;

use App\Actions\Resultats\EtablirPalmares;
use App\Enums\Editions\StatutEdition;
use App\Models\Candidate;
use App\Models\Edition;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Palmarès d'une édition (R53), à partir des seuls résultats publiés : femmes modèles et lauréates de chaque phase,
 * filtrés par édition, phase et recherche sans recharger la page. Ouvert à tout utilisateur connecté.
 */
class Palmares extends Component
{
    #[Url]
    public string $annee = '';

    #[Url]
    public string $phase = '';

    #[Url]
    public string $recherche = '';

    public function boot(): void
    {
        abort_unless(Auth::check(), 403);
    }

    /**
     * Une édition demandée dans l'adresse (/palmares/{edition}) devient l'année affichée.
     */
    public function mount(?int $editionId = null): void
    {
        if ($editionId !== null && $this->annee === '') {
            $this->annee = (string) Edition::whereKey($editionId)->value('annee');
        }
    }

    public function updatedAnnee(): void
    {
        $this->phase = '';
    }

    /**
     * Édition demandée, sinon l'édition en cours, sinon la plus récente.
     */
    #[Computed]
    public function edition(): ?Edition
    {
        return ((int) $this->annee > 0 ? Edition::where('annee', (int) $this->annee)->first() : null)
            ?? Edition::where('statut', StatutEdition::EnCours->value)->first()
            ?? Edition::orderByDesc('annee')->first();
    }

    /**
     * @return array{femmes_modeles: Collection<int, Candidate>, phases: Collection<int, array<string, mixed>>}|null
     */
    #[Computed]
    public function palmares(): ?array
    {
        if ($this->edition === null) {
            return null;
        }

        $palmares = app(EtablirPalmares::class)->handle($this->edition);
        $terme = mb_strtolower(trim($this->recherche));
        $phase = (int) $this->phase ?: null;

        $palmares['phases'] = $palmares['phases']
            ->filter(fn (array $ligne): bool => $phase === null || $ligne['numero'] === $phase)
            ->map(fn (array $ligne): array => [...$ligne, 'laureates' => $ligne['laureates']
                ->filter(fn (array $l): bool => $terme === '' || str_contains(mb_strtolower($l['agent'].' '.$l['matricule'].' '.$l['scrutin']), $terme))
                ->values()])
            ->values();

        return $palmares;
    }

    public function render(): View
    {
        return view('livewire.resultats.palmares', [
            'edition' => $this->edition,
            'palmares' => $this->palmares,
            'annees' => Edition::orderByDesc('annee')->pluck('annee'),
            'phases' => $this->edition?->phases()->orderBy('numero')->get(['numero', 'libelle']) ?? collect(),
            'totalLaureates' => $this->palmares === null ? 0 : $this->palmares['phases']->sum(fn (array $ligne): int => $ligne['laureates']->count()),
            'filtresActifs' => trim($this->recherche) !== '' || $this->phase !== '',
        ]);
    }
}
