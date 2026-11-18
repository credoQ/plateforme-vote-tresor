<?php

namespace App\Actions\Fortify;

use App\Actions\Audit\Journaliser;
use App\Enums\Audit\ActionAuditee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * Réinitialisation par l'utilisateur de son mot de passe oublié, depuis le lien reçu par e-mail.
 * Le mot de passe choisi est définitif : il n'a pas à être changé à la connexion suivante.
 */
class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function __construct(private Journaliser $journal) {}

    /**
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, ['password' => $this->passwordRules()], $this->passwordMessages())->validate();

        DB::transaction(function () use ($user, $input): void {
            $user->forceFill([
                'password' => $input['password'],
                'mot_de_passe_a_changer' => false,
            ])->save();

            $this->journal->handle(ActionAuditee::MotDePasseReinitialise, $user, ['login' => $user->login, 'par' => 'lien e-mail'], $user);
        });
    }
}
