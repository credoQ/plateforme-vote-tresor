<?php

namespace App\Actions\Acces;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Change le mot de passe de l'utilisateur connecté, obligatoire à la première connexion (module 01).
 */
class ChangerMotDePasse
{
    /**
     * @param  array{mot_de_passe_actuel: string, mot_de_passe: string, mot_de_passe_confirmation: string}  $donnees
     *
     * @throws ValidationException
     */
    public function handle(User $utilisateur, array $donnees): void
    {
        Validator::make($donnees, [
            'mot_de_passe_actuel' => ['required', 'string'],
            'mot_de_passe' => ['required', 'string', 'confirmed', Password::min(10)->letters()->numbers(), 'different:mot_de_passe_actuel'],
        ], [
            'mot_de_passe.confirmed' => 'La confirmation ne correspond pas au nouveau mot de passe.',
            'mot_de_passe.min' => 'Le mot de passe doit contenir au moins 10 caractères.',
            'mot_de_passe.letters' => 'Le mot de passe doit contenir au moins une lettre.',
            'mot_de_passe.numbers' => 'Le mot de passe doit contenir au moins un chiffre.',
            'mot_de_passe.different' => 'Le nouveau mot de passe doit être différent de l’actuel.',
            'required' => 'Ce champ est obligatoire.',
        ])->after(function ($validator) use ($utilisateur, $donnees): void {
            if (! Hash::check((string) ($donnees['mot_de_passe_actuel'] ?? ''), $utilisateur->password)) {
                $validator->errors()->add('mot_de_passe_actuel', 'Le mot de passe actuel est incorrect.');
            }
        })->validate();

        $utilisateur->forceFill([
            'password' => $donnees['mot_de_passe'],
            'mot_de_passe_a_changer' => false,
        ])->save();
    }
}
