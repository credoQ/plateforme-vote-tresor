<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

/**
 * Authentification par Laravel Fortify (R42) : connexion par matricule ou identifiant, mot de passe oublié
 * par e-mail, changement du mot de passe et double authentification facultative (application TOTP).
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        // Seuls les comptes actifs se connectent (R1).
        Fortify::authenticateUsing(function (Request $request): ?User {
            $utilisateur = User::where('login', trim((string) $request->input('login')))->first();

            return $utilisateur !== null && $utilisateur->actif && Hash::check((string) $request->input('password'), $utilisateur->password)
                ? $utilisateur
                : null;
        });

        Fortify::loginView(fn () => view('acces.connexion'));
        Fortify::requestPasswordResetLinkView(fn () => view('acces.mot-de-passe-oublie'));
        Fortify::resetPasswordView(fn (Request $request) => view('acces.reinitialisation', ['request' => $request]));
        Fortify::confirmPasswordView(fn () => view('acces.confirmation-mot-de-passe'));
        Fortify::twoFactorChallengeView(fn () => view('acces.double-authentification'));

        ResetPassword::toMailUsing(fn (object $notifiable, string $jeton): MailMessage => (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe')
            ->greeting('Bonjour,')
            ->line('Vous avez demandé à réinitialiser le mot de passe de votre compte sur la plateforme de vote de la Femme modèle du Trésor.')
            ->action('Choisir un nouveau mot de passe', url(route('password.reset', ['token' => $jeton, 'email' => $notifiable->getEmailForPasswordReset()], false)))
            ->line('Ce lien expire dans '.config('auth.passwords.users.expire').' minutes. Si vous n’êtes pas à l’origine de la demande, ignorez ce message.')
            ->salutation('Le comité d’organisation de la Femme modèle du Trésor'));

        RateLimiter::for('login', function (Request $request) {
            $cle = Str::transliterate(Str::lower((string) $request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($cle);
        });

        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id')));
    }
}
