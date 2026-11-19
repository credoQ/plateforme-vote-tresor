<?php

namespace App\Actions\Vote;

use App\Models\Inscription;
use App\Models\User;
use App\Notifications\AccuseDeVoteEnregistre;
use Illuminate\Support\Facades\Notification;

/**
 * Envoie l'accusé de vote par e-mail, après l'enregistrement du vote, à l'adresse du compte, sinon de la fiche agent.
 * Un électeur sans adresse retrouve son accusé sur la plateforme.
 */
class EnvoyerAccuseDeVote
{
    public function handle(User $electeur, Inscription $inscription): bool
    {
        $email = $electeur->adresseDeConnexion();
        if ($email === null) {
            return false;
        }

        Notification::route('mail', $email)->notify(new AccuseDeVoteEnregistre($inscription->id));

        return true;
    }
}
