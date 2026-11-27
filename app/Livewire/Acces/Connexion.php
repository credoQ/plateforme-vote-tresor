<?php

namespace App\Livewire\Acces;

use App\Actions\Acces\DemanderCodeConnexion;
use App\Actions\Acces\VerifierCodeAcces;
use App\Actions\Acces\VerifierCodeConnexion;
use App\Enums\Acces\ModeConnexion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Connexion sans mot de passe (R42), en deux étapes dans la même page : le matricule, puis le code reçu par e-mail.
 *
 * Le matricule en cours de connexion est gardé en session (jamais pris dans les propriétés publiques, que le
 * navigateur peut modifier) ; les propriétés publiques ne servent qu'à l'affichage et à la saisie.
 */
class Connexion extends Component
{
    private const ENVOIS_MAX = 3;

    private const FENETRE_ENVOIS = 600;

    private const ESSAIS_CODE_PAR_MINUTE = 10;

    /**
     * Délai, en secondes, avant de pouvoir demander un nouveau code.
     */
    public const DELAI_RENVOI = 60;

    public string $etape = 'matricule';

    public string $login = '';

    public bool $souvenir = false;

    public string $code = '';

    public function mount(): void
    {
        if (session()->has('connexion.login')) {
            $this->etape = 'code';
            $this->login = (string) session('connexion.login');
        }
    }

    public function envoyer(DemanderCodeConnexion $demander): void
    {
        $this->login = trim((string) $this->validate(['login' => ['required', 'string', 'max:60']], ['login.required' => 'Saisissez votre matricule.'])['login']);

        $this->limiterLesEnvois($this->login);
        $demander->handle($this->login, request()->ip());

        session()->put('connexion.login', $this->login);
        session()->put('connexion.souvenir', $this->souvenir);
        session()->put('connexion.envoye_le', now()->getTimestamp());

        $this->reset('code');
        $this->resetErrorBag();
        $this->etape = 'code';
        $this->dispatch('code-efface');
        $this->dispatch('code-envoye', attente: self::DELAI_RENVOI);
    }

    public function verifier(VerifierCodeConnexion $verifier): void
    {
        $login = session('connexion.login');
        if ($login === null) {
            $this->changerDeMatricule();

            return;
        }

        $code = (string) $this->validate(['code' => ['required', 'string', 'max:12']], ['code.required' => 'Saisissez le code reçu par e-mail.'])['code'];

        // R42 : essais limités par matricule et par poste ; des agents qui partagent une adresse IP ne se bloquent pas.
        $cle = 'saisie-code:'.Str::transliterate(Str::lower($login)).'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($cle, self::ESSAIS_CODE_PAR_MINUTE)) {
            throw ValidationException::withMessages(['code' => 'Trop d’essais. Réessayez dans une minute.']);
        }
        RateLimiter::hit($cle, 60);

        $utilisateur = $verifier->handle($login, $code);

        Auth::login($utilisateur, (bool) session('connexion.souvenir', false));
        session()->forget(['connexion.login', 'connexion.souvenir', 'connexion.envoye_le']);
        session()->regenerate();

        $this->redirectIntended($utilisateur->pageDAccueil());
    }

    /**
     * Mode « code imprimé » (ModeConnexion::CodeImprime) : matricule et code personnel du coupon, en une étape.
     */
    public function seConnecter(VerifierCodeAcces $verifier): void
    {
        abort_unless(ModeConnexion::codeImprime(), 404);

        $donnees = $this->validate(
            ['login' => ['required', 'string', 'max:60'], 'code' => ['required', 'string', 'max:20']],
            ['login.required' => 'Saisissez votre matricule.', 'code.required' => 'Saisissez le code de votre coupon.'],
        );
        $login = trim((string) $donnees['login']);

        // R42 : essais limités par matricule et par poste, comme pour le code envoyé par e-mail.
        $cle = 'saisie-code:'.Str::transliterate(Str::lower($login)).'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($cle, self::ESSAIS_CODE_PAR_MINUTE)) {
            throw ValidationException::withMessages(['code' => 'Trop d’essais. Réessayez dans une minute.']);
        }
        RateLimiter::hit($cle, 60);

        $utilisateur = $verifier->handle($login, (string) $donnees['code']);

        Auth::login($utilisateur, $this->souvenir);
        session()->regenerate();

        $this->redirectIntended($utilisateur->pageDAccueil());
    }

    public function renvoyer(DemanderCodeConnexion $demander): void
    {
        $login = session('connexion.login');
        if ($login === null) {
            $this->changerDeMatricule();

            return;
        }

        if ($this->attenteRenvoi > 0) {
            throw ValidationException::withMessages(['code' => "Patientez encore {$this->attenteRenvoi} seconde(s) avant de demander un nouveau code."]);
        }

        $this->limiterLesEnvois($login);
        $demander->handle($login, request()->ip());
        session()->put('connexion.envoye_le', now()->getTimestamp());
        unset($this->attenteRenvoi);

        $this->reset('code');
        $this->resetErrorBag();
        $this->dispatch('code-efface');
        $this->dispatch('code-envoye', attente: self::DELAI_RENVOI);
        $this->dispatch('notifier', message: 'Un nouveau code vient d’être envoyé.', ton: 'succes');
    }

    public function changerDeMatricule(): void
    {
        session()->forget(['connexion.login', 'connexion.souvenir', 'connexion.envoye_le']);
        $this->reset('code');
        $this->resetErrorBag();
        $this->etape = 'matricule';
        $this->dispatch('code-efface');
    }

    /**
     * Secondes à attendre avant de pouvoir redemander un code (0 si c'est possible).
     */
    #[Computed]
    public function attenteRenvoi(): int
    {
        return max(0, (int) session('connexion.envoye_le', 0) + self::DELAI_RENVOI - now()->getTimestamp());
    }

    #[Computed]
    public function validiteMinutes(): int
    {
        return DemanderCodeConnexion::VALIDITE_MINUTES;
    }

    /**
     * Trois envois au plus par matricule et par poste toutes les dix minutes.
     *
     * @throws ValidationException
     */
    private function limiterLesEnvois(string $login): void
    {
        $cle = 'code-connexion:'.Str::transliterate(Str::lower($login)).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($cle, self::ENVOIS_MAX)) {
            $minutes = (int) ceil(RateLimiter::availableIn($cle) / 60);

            throw ValidationException::withMessages([$this->etape === 'code' ? 'code' : 'login' => "Trop de demandes de code. Réessayez dans {$minutes} minute(s)."]);
        }

        RateLimiter::hit($cle, self::FENETRE_ENVOIS);
    }

    public function render(): View
    {
        return view('livewire.acces.connexion', ['codeImprime' => ModeConnexion::codeImprime()]);
    }
}
