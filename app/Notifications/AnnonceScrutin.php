<?php

namespace App\Notifications;

use App\Models\Scrutin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Annonce envoyée par e-mail aux inscrits d'un scrutin dont le contact est connu (module 08) :
 * ouverture du vote ou publication des résultats. Elle ne contient jamais de voix avant la publication (R46).
 * Chaque e-mail est un job de la file « mails », relancé en cas d'échec du serveur de messagerie.
 */
class AnnonceScrutin extends Notification implements ShouldQueue
{
    use Queueable;

    public const OUVERTURE = 'ouverture';

    public const PUBLICATION = 'publication';

    public const FILE = 'mails';

    public int $tries = 3;

    public function __construct(public Scrutin $scrutin, public string $type)
    {
        $this->onQueue(self::FILE);
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
        $fuseau = 'Africa/Porto-Novo';
        $message = (new MailMessage)->greeting('Bonjour,')->salutation('Le comité d’organisation de la Femme modèle du Trésor');

        if ($this->type === self::OUVERTURE) {
            return $message
                ->subject("Le vote est ouvert : {$this->scrutin->libelle}")
                ->line("Le scrutin « {$this->scrutin->libelle} » est ouvert jusqu’au {$this->scrutin->date_heure_fermeture->timezone($fuseau)->format('d/m/Y à H:i')}.")
                ->line('Vous êtes inscrit sur sa liste électorale. Votre vote est secret.')
                ->action('Voter', route('espace'));
        }

        return $message
            ->subject("Résultats publiés : {$this->scrutin->libelle}")
            ->line("Les résultats du scrutin « {$this->scrutin->libelle} » sont publiés.")
            ->action('Consulter les résultats', route('espace'));
    }
}
