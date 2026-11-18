<?php

namespace App\Http\Controllers\Acces;

use App\Actions\Acces\DemanderCodeConnexion;
use App\Actions\Acces\VerifierCodeConnexion;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Connexion sans mot de passe (R42) : l'agent saisit son matricule, reçoit un code par e-mail et le saisit.
 */
class ConnexionController extends Controller
{
    private const ENVOIS_MAX = 3;

    private const FENETRE_ENVOIS = 600;

    public function create(): View
    {
        return view('acces.connexion');
    }

    public function envoyer(Request $request, DemanderCodeConnexion $demander): RedirectResponse
    {
        $login = trim((string) $request->validate(['login' => ['required', 'string', 'max:60']], ['login.required' => 'Saisissez votre matricule.'])['login']);

        $this->limiterLesEnvois($request, $login);
        $demander->handle($login, $request->ip());

        $request->session()->put('connexion.login', $login);
        $request->session()->put('connexion.souvenir', $request->boolean('remember'));

        return redirect()->route('login.code');
    }

    public function code(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('connexion.login')) {
            return redirect()->route('login');
        }

        return view('acces.code-connexion', ['login' => $request->session()->get('connexion.login')]);
    }

    public function verifier(Request $request, VerifierCodeConnexion $verifier): RedirectResponse
    {
        $login = $request->session()->get('connexion.login');
        if ($login === null) {
            return redirect()->route('login');
        }

        $code = (string) $request->validate(['code' => ['required', 'string', 'max:12']], ['code.required' => 'Saisissez le code reçu par e-mail.'])['code'];
        $utilisateur = $verifier->handle($login, $code);

        Auth::login($utilisateur, (bool) $request->session()->get('connexion.souvenir', false));
        $request->session()->forget(['connexion.login', 'connexion.souvenir']);
        $request->session()->regenerate();

        return redirect()->intended(route('espace'));
    }

    public function renvoyer(Request $request, DemanderCodeConnexion $demander): RedirectResponse
    {
        $login = $request->session()->get('connexion.login');
        if ($login === null) {
            return redirect()->route('login');
        }

        $this->limiterLesEnvois($request, $login);
        $demander->handle($login, $request->ip());

        return redirect()->route('login.code')->with('status', 'Un nouveau code vient d’être envoyé.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Trois envois au plus par matricule et par poste toutes les dix minutes.
     *
     * @throws ValidationException
     */
    private function limiterLesEnvois(Request $request, string $login): void
    {
        $cle = 'code-connexion:'.Str::transliterate(Str::lower($login)).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($cle, self::ENVOIS_MAX)) {
            $minutes = (int) ceil(RateLimiter::availableIn($cle) / 60);

            throw ValidationException::withMessages(['login' => "Trop de demandes de code. Réessayez dans {$minutes} minute(s)."]);
        }

        RateLimiter::hit($cle, self::FENETRE_ENVOIS);
    }
}
