<?php

namespace App\Notifications;

use App\Models\Candidate;
use App\Models\Phase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Informe une lauréate, à la publication des résultats de son scrutin, qu'elle est qualifiée pour la phase suivante (R35, R52).
 * Envoyée par la file « mails », comme les annonces.
 */
class QualificationCandidate extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Candidate $candidate, public Phase $phaseSuivante)
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
        $scrutin = $this->candidate->scrutin;

        return (new MailMessage)
            ->subject("Vous êtes qualifiée pour la {$this->phaseSuivante->libelle}")
            ->greeting("Félicitations {$this->candidate->agent->nomComplet()},")
            ->line("Les résultats du scrutin « {$scrutin->libelle} » sont publiés : vous êtes classée au rang {$this->candidate->rang} avec {$this->candidate->nb_voix} voix.")
            ->line("Vous êtes qualifiée pour la {$this->phaseSuivante->libelle}. Vous y serez candidate d’office ; le comité d’organisation vous communiquera les dates du vote.")
            ->action('Accéder à mon espace', route('espace'))
            ->salutation('Le comité d’organisation de la Femme modèle du Trésor');
    }
}
