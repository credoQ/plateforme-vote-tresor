<?php

namespace App\Http\Requests\Acces;

use App\Actions\Audit\Journaliser;
use App\Enums\Audit\ActionAuditee;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Connexion par matricule (ou identifiant) et mot de passe (R42), limitée à cinq essais par minute.
 */
class ConnexionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:60'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'login.required' => 'Saisissez votre matricule.',
            'password.required' => 'Saisissez votre mot de passe.',
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authentifier(): void
    {
        $this->verifierLimite();

        $identifiants = ['login' => trim($this->string('login')), 'password' => $this->string('password')->toString(), 'actif' => true];

        if (! Auth::attempt($identifiants, $this->boolean('se_souvenir'))) {
            RateLimiter::hit($this->cleLimite(), 60);
            app(Journaliser::class)->handle(ActionAuditee::ConnexionEchouee, null, [], null, mb_substr($identifiants['login'], 0, 60));

            throw ValidationException::withMessages(['login' => 'Matricule ou mot de passe incorrect, ou compte désactivé.']);
        }

        RateLimiter::clear($this->cleLimite());
    }

    /**
     * @throws ValidationException
     */
    private function verifierLimite(): void
    {
        if (! RateLimiter::tooManyAttempts($this->cleLimite(), 5)) {
            return;
        }

        event(new Lockout($this));
        $secondes = RateLimiter::availableIn($this->cleLimite());

        throw ValidationException::withMessages(['login' => "Trop de tentatives. Réessayez dans {$secondes} secondes."]);
    }

    private function cleLimite(): string
    {
        return Str::transliterate(Str::lower($this->string('login')).'|'.$this->ip());
    }
}
