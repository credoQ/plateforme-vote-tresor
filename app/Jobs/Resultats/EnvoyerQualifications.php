<?php

namespace App\Jobs\Resultats;

use App\Models\Candidate;
use App\Models\Scrutin;
use App\Notifications\AnnonceScrutin;
use App\Notifications\ProclamationFemmeModele;
use App\Notifications\QualificationCandidate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Après la publication des résultats d'un scrutin, prévient chaque lauréate : qualifiée pour la phase suivante
 * en phases 1 et 2 (R35, R52), Femme modèle du Trésor en finale (R53).
 * Une lauréate sans adresse e-mail voit l'information sur la plateforme.
 */
class EnvoyerQualifications implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $scrutinId)
    {
        $this->onQueue(AnnonceScrutin::FILE);
    }

    public function handle(): void
    {
        $scrutin = Scrutin::with('phase')->findOrFail($this->scrutinId);
        $phaseSuivante = $scrutin->phase->phaseSuivante();

        $scrutin->laureates()->actives()->with(['scrutin.phase.edition', 'agent.compte'])->get()
            ->each(function (Candidate $laureate) use ($phaseSuivante): void {
                $email = $laureate->agent->adresseDeNotification();
                if ($email === null) {
                    return;
                }

                Notification::route('mail', $email)->notify($phaseSuivante === null
                    ? new ProclamationFemmeModele($laureate)
                    : new QualificationCandidate($laureate, $phaseSuivante));
            });
    }
}
