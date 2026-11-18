<?php

namespace App\Actions\Audit;

use App\Enums\Audit\ActionAuditee;
use App\Models\JournalAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Enregistre une opération sensible au journal d'audit (R56) : utilisateur, date-heure, action, entité et adresse IP.
 *
 * L'appel a lieu dans la transaction de l'opération : si elle échoue, rien n'est journalisé.
 * Ne jamais y mettre le choix d'un électeur ni un mot de passe (R43, R57).
 */
class Journaliser
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function handle(ActionAuditee $action, ?Model $entite = null, array $details = [], ?User $auteur = null, ?string $login = null): JournalAudit
    {
        $auteur ??= Auth::user();

        return JournalAudit::create([
            'date_heure' => now(),
            'user_id' => $auteur?->getKey(),
            'login' => $auteur?->login ?? $login ?? (app()->runningInConsole() ? 'console' : null),
            'action' => $action,
            'entite_type' => $entite === null ? null : class_basename($entite),
            'entite_id' => $entite?->getKey(),
            'details' => $details === [] ? null : $details,
            'adresse_ip' => app()->runningInConsole() && ! app()->runningUnitTests() ? null : request()->ip(),
        ]);
    }
}
