<?php

namespace App\Notifications;

use App\Actions\Vote\GenererAccuseDeVote;
use App\Models\Inscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Accusé de vote envoyé par e-mail à l'électeur après son vote, avec le PDF en pièce jointe (file « mails »).
 * Le job ne porte que l'identifiant de l'inscription : ni le choix, ni le bulletin (R43).
 */
class AccuseDeVoteEnregistre extends Notification implements ShouldQueue
{
    use Queueable;

    public const FILE = 'mails';

    public int $tries = 3;

    public function __construct(public int $inscriptionId)
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
        $inscription = Inscription::with(['agent', 'structure', 'scrutin.phase.edition'])->findOrFail($this->inscriptionId);
        $generer = app(GenererAccuseDeVote::class);

        return (new MailMessage)
            ->subject("Votre vote est enregistré : {$inscription->scrutin->libelle}")
            ->markdown('mail.accuse-de-vote', ['inscription' => $inscription, 'scrutin' => $inscription->scrutin])
            ->attachData($generer->handle($inscription), $generer->nomDeFichier($inscription), ['mime' => 'application/pdf']);
    }
}
