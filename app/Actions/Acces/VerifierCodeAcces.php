<?php

namespace App\Actions\Acces;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Validation\ValidationException;

/**
 * Connexion du mode « code imprimé » : matricule et code personnel du coupon (GenererCodesAcces). Compte actif
 * seulement ; comparaison à temps constant. Un échec est journalisé comme une connexion échouée.
 */
class VerifierCodeAcces
{
    /**
     * @throws ValidationException
     */
    public function handle(string $login, string $code): User
    {
        $utilisateur = User::where('login', trim($login))->where('actif', true)->first();
        $saisi = GenererCodesAcces::normaliser($code);

        $valide = $utilisateur !== null
            && $utilisateur->code_acces !== null
            && $saisi !== ''
            && hash_equals($utilisateur->code_acces, $saisi);

        if (! $valide) {
            event(new Failed('web', $utilisateur, ['login' => $login]));

            throw ValidationException::withMessages(['code' => 'Matricule ou code incorrect. Vérifiez votre coupon, ou adressez-vous au comité.']);
        }

        return $utilisateur;
    }
}
