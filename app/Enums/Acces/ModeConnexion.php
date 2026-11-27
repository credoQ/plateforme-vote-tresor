<?php

namespace App\Enums\Acces;

/**
 * Mode de connexion de la plateforme (CONNEXION_MODE dans le .env).
 *
 * - Email : code à usage unique envoyé par e-mail à chaque connexion (R42).
 * - CodeImprime : code personnel imprimé sur un coupon remis par le comité, valable pour toute l'édition ; aucun
 *   e-mail n'est envoyé (mode de secours quand le serveur de messagerie n'est pas disponible).
 */
enum ModeConnexion: string
{
    case Email = 'email';
    case CodeImprime = 'code_imprime';

    public static function actuel(): self
    {
        return self::tryFrom((string) config('auth.mode_connexion')) ?? self::Email;
    }

    public static function codeImprime(): bool
    {
        return self::actuel() === self::CodeImprime;
    }
}
