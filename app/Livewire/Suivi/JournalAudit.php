<?php

namespace App\Livewire\Suivi;

use App\Enums\Audit\ActionAuditee;
use App\Models\JournalAudit as EntreeJournal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Journal d'audit (R56), en lecture seule pour l'observateur, le comité et l'administrateur,
 * filtré sans recharger la page. Les dates se lisent à l'heure du Bénin.
 */
class JournalAudit extends Component
{
    use WithPagination;

    private const FUSEAU = 'Africa/Porto-Novo';

    #[Url]
    public string $action = '';

    #[Url]
    public string $login = '';

    #[Url]
    public string $du = '';

    #[Url]
    public string $au = '';

    /**
     * Contrôle des droits à chaque requête du composant, mises à jour comprises.
     */
    public function boot(): void
    {
        Gate::authorize('observer');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, EntreeJournal>
     */
    #[Computed]
    public function entrees(): LengthAwarePaginator
    {
        $action = ActionAuditee::tryFrom($this->action);
        $login = trim($this->login);
        $du = $this->date($this->du);
        $au = $this->date($this->au);

        return EntreeJournal::query()
            ->when($action !== null, fn ($q) => $q->where('action', $action->value))
            ->when($login !== '', fn ($q) => $q->whereLike('login', "%{$login}%"))
            ->when($du !== null, fn ($q) => $q->where('date_heure', '>=', $du->copy()->startOfDay()->utc()))
            ->when($au !== null, fn ($q) => $q->where('date_heure', '<=', $au->copy()->endOfDay()->utc()))
            ->orderByDesc('date_heure')
            ->orderByDesc('id')
            ->paginate(config('app.lignes_par_page'));
    }

    #[Computed]
    public function filtresActifs(): bool
    {
        return $this->action !== '' || trim($this->login) !== '' || $this->du !== '' || $this->au !== '';
    }

    public function paginationView(): string
    {
        return 'pagination.tresor-livewire';
    }

    public function render(): View
    {
        return view('livewire.suivi.journal-audit');
    }

    private function date(string $valeur): ?Carbon
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur) !== 1) {
            return null;
        }

        return Carbon::createFromFormat('!Y-m-d', $valeur, self::FUSEAU);
    }
}
