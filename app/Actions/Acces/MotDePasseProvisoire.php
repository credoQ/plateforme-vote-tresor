<?php

namespace App\Actions\Acces;

/**
 * Génère un mot de passe provisoire lisible, à remettre par le point focal et à changer à la première connexion.
 * Les caractères ambigus (0, O, 1, l, I) sont exclus.
 */
class MotDePasseProvisoire
{
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';

    public function generer(int $longueur = 10): string
    {
        $motDePasse = '';
        for ($i = 0; $i < $longueur; $i++) {
            $motDePasse .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $motDePasse;
    }
}
