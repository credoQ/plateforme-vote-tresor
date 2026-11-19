<?php

namespace App\Jobs\Suivi;

use App\Models\Inscription;
use App\Models\Scrutin;
use App\Notifications\AnnonceScrutin;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Envoie une annonce aux inscrits d'un scrutin qui ont une adresse e-mail (celle du compte, sinon celle de la fiche agent).
 * Les agents sans contact voient l'annonce sur la plateforme.
 */
class EnvoyerAnnonceScrutin implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $scrutinId, public string $type)
    {
        $this->onQueue(AnnonceScrutin::FILE);
    }

    public function handle(): void
    {
        $scrutin = Scrutin::findOrFail($this->scrutinId);

        Inscription::query()
            ->where('scrutin_id', $scrutin->id)
            ->with(['agent:id,email', 'agent.compte:id,agent_id,email,actif'])
            ->chunkById(500, function ($inscriptions) use ($scrutin): void {
                foreach ($inscriptions as $inscription) {
                    $email = $inscription->agent?->compte?->email ?: $inscription->agent?->email;
                    if (filled($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        Notification::route('mail', $email)->notify(new AnnonceScrutin($scrutin, $this->type));
                    }
                }
            });
    }
}
