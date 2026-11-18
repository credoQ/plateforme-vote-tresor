<?php

namespace App\Actions\Acces;

use App\Models\User;

/**
 * Réinitialise le mot de passe d'un utilisateur : un mot de passe provisoire est généré, à remettre
 * à l'intéressé et à changer à la prochaine connexion. Ses sessions « se souvenir de moi » sont invalidées.
 */
class ReinitialiserMotDePasse
{
    public function __construct(private MotDePasseProvisoire $motDePasse) {}

    public function handle(User $utilisateur): string
    {
        $motDePasse = $this->motDePasse->generer();

        $utilisateur->forceFill([
            'password' => $motDePasse,
            'mot_de_passe_a_changer' => true,
            'remember_token' => null,
        ])->save();

        return $motDePasse;
    }
}
