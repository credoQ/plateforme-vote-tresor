<?php

namespace App\Actions\Acces;

use App\Actions\Audit\Journaliser;
use App\Enums\Audit\ActionAuditee;
use App\Models\CodeConnexion;
use App\Models\User;
use App\Notifications\CodeDeConnexion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Envoie un code de connexion à usage unique à l'adresse e-mail du compte (R42).
 *
 * Rien n'indique à l'écran si le matricule existe : un compte inconnu, désactivé ou sans adresse e-mail
 * ne reçoit simplement rien. Un nouveau code annule les précédents.
 */
class DemanderCodeConnexion
{
    public const VALIDITE_MINUTES = 10;

    public function __construct(private Journaliser $journal) {}

    public function handle(string $login, ?string $adresseIp = null): void
    {
        $utilisateur = User::with('agent')->where('login', trim($login))->first();
        $email = $utilisateur?->adresseDeConnexion();

        if ($utilisateur === null || ! $utilisateur->actif || $email === null) {
            $this->journal->handle(ActionAuditee::ConnexionEchouee, null, ['motif' => $utilisateur === null ? 'compte inconnu' : ($utilisateur->actif ? 'aucune adresse e-mail' : 'compte désactivé')], null, mb_substr(trim($login), 0, 60));

            return;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($utilisateur, $code, $adresseIp): void {
            CodeConnexion::where('user_id', $utilisateur->id)->whereNull('utilise_le')->update(['utilise_le' => now()]);
            CodeConnexion::create([
                'user_id' => $utilisateur->id,
                'empreinte' => self::empreinte($code),
                'expire_le' => now()->addMinutes(self::VALIDITE_MINUTES),
                'adresse_ip' => $adresseIp,
            ]);
            $this->journal->handle(ActionAuditee::CodeConnexionEnvoye, $utilisateur, [], $utilisateur);
        });

        Notification::route('mail', $email)->notifyNow(new CodeDeConnexion($code, self::VALIDITE_MINUTES));
    }

    public static function empreinte(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
