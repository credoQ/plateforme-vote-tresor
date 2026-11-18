<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Code de connexion à usage unique (R42). Envoyé immédiatement, sans file d'attente : l'agent l'attend pour se connecter.
 */
class CodeDeConnexion extends Notification
{
    public function __construct(public string $code, public int $minutes) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Votre code de connexion : {$this->code}")
            ->greeting('Bonjour,')
            ->line('Votre code de connexion à la plateforme de vote de la Femme modèle du Trésor :')
            ->line("**{$this->code}**")
            ->line("Il est valable {$this->minutes} minutes et ne sert qu’une fois. Ne le communiquez à personne.")
            ->line('Si vous n’êtes pas à l’origine de cette demande, ignorez ce message.')
            ->salutation('Le comité d’organisation de la Femme modèle du Trésor');
    }
}
