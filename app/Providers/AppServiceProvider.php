<?php

namespace App\Providers;

use App\Enums\Acces\CodeRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Droits par profil (R2). Les droits sur un objet précis (candidate, agent, inscription) sont dans app/Policies.
     */
    public function boot(): void
    {
        Gate::define('administrer', fn (User $user): bool => $user->aLeRole(CodeRole::Administrateur));
        Gate::define('piloter', fn (User $user): bool => $user->aLeRole(CodeRole::Comite));
        Gate::define('controler-listes', fn (User $user): bool => $user->aLeRole(CodeRole::Comite, CodeRole::PointFocal));
        Gate::define('observer', fn (User $user): bool => $user->aLeRole(CodeRole::Observateur, CodeRole::Comite, CodeRole::Administrateur));
        Gate::define('voter', fn (User $user): bool => $user->agent_id !== null && $user->aLeRole(CodeRole::Electeur));
    }
}
