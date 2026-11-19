<?php

namespace App\Notifications;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Code de connexion à usage unique (R42), envoyé par la file d'attente prioritaire « urgent ».
 *
 * Le job est chiffré (le code n'apparaît pas en clair dans la table des jobs) et n'est plus tenté
 * au-delà de la validité du code.
 */
class CodeDeConnexion extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public const FILE = 'urgent';

    public int $tries = 3;

    public CarbonImmutable $retryUntil;

    public function __construct(public string $code, public int $minutes)
    {
        $this->onQueue(self::FILE);
        $this->retryUntil = CarbonImmutable::now()->addMinutes($minutes);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [5, 30];
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
