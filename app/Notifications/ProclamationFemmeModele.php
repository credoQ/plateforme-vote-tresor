<?php

namespace App\Notifications;

use App\Models\Candidate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Informe la lauréate de la finale, à la publication des résultats, qu'elle est la Femme modèle du Trésor de l'édition (R53).
 * Envoyée par la file « mails », comme les annonces.
 */
class ProclamationFemmeModele extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Candidate $candidate)
    {
        $this->onQueue(AnnonceScrutin::FILE);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $annee = $this->candidate->scrutin->phase->edition->annee;

        return (new MailMessage)
            ->subject("Vous êtes la Femme modèle du Trésor {$annee}")
            ->greeting("Félicitations {$this->candidate->agent->nomComplet()},")
            ->line("Les résultats de la finale sont publiés : avec {$this->candidate->nb_voix} voix, vous êtes élue Femme modèle du Trésor {$annee}.")
            ->line('Le comité d’organisation vous contactera pour la suite de l’édition.')
            ->action('Accéder à mon espace', route('espace'))
            ->salutation('Le comité d’organisation de la Femme modèle du Trésor');
    }
}
