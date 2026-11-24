<?php

namespace App\Livewire\Suivi;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Tâches de la file d'attente en échec, filtrées par texte, file et jour, sans recharger la page. La relance et la
 * suppression restent des formulaires envoyés au contrôleur, qui les journalise (R56).
 */
class ListeTachesEchouees extends Component
{
    use WithPagination;

    private const FUSEAU = 'Africa/Porto-Novo';

    #[Url]
    public string $recherche = '';

    #[Url]
    public string $file = '';

    #[Url]
    public string $jour = '';

    /**
     * Contrôle des droits à chaque requête du composant, mises à jour comprises.
     */
    public function boot(): void
    {
        Gate::authorize('administrer');
    }

    public function updated(string $propriete): void
    {
        if (in_array($propriete, ['recherche', 'file', 'jour'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, object>
     */
    #[Computed]
    public function taches(): LengthAwarePaginator
    {
        $recherche = trim($this->recherche);
        $jour = $this->jour !== '' ? Carbon::createFromFormat('Y-m-d', $this->jour, self::FUSEAU) : null;

        return DB::table('failed_jobs')
            ->when($recherche !== '', fn ($q) => $q->where(fn ($r) => $r->whereLike('payload', "%{$recherche}%")->orWhereLike('exception', "%{$recherche}%")))
            ->when($this->file !== '', fn ($q) => $q->where('queue', $this->file))
            ->when($jour !== null, fn ($q) => $q->whereBetween('failed_at', [$jour->copy()->startOfDay()->utc(), $jour->copy()->endOfDay()->utc()]))
            ->orderByDesc('failed_at')
            ->paginate(config('app.lignes_par_page'))
            ->through(function (object $ligne): object {
                $charge = json_decode($ligne->payload, true) ?? [];

                return (object) [
                    'uuid' => $ligne->uuid,
                    'tache' => class_basename($charge['displayName'] ?? 'Tâche inconnue'),
                    'file' => $ligne->queue,
                    'tentatives' => $charge['attempts'] ?? null,
                    'echec' => Carbon::parse($ligne->failed_at),
                    'erreur' => Str::of($ligne->exception)->before("\n")->limit(220)->toString(),
                    'trace' => Str::limit($ligne->exception, 4000),
                ];
            });
    }

    /**
     * @return Collection<int, string>
     */
    #[Computed]
    public function files(): Collection
    {
        return DB::table('failed_jobs')->distinct()->orderBy('queue')->pluck('queue');
    }

    public function paginationView(): string
    {
        return 'pagination.tresor-livewire';
    }

    public function render(): View
    {
        return view('livewire.suivi.liste-taches-echouees', [
            'filtresActifs' => trim($this->recherche) !== '' || $this->file !== '' || $this->jour !== '',
        ]);
    }
}
