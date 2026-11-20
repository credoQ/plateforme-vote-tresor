<?php

namespace App\Actions\Vote;

use App\Actions\Documents\CodeQr;
use App\Actions\Documents\LienVerification;
use App\Models\Inscription;
use Barryvdh\DomPDF\Facade\Pdf;
use InvalidArgumentException;

/**
 * Accusé de vote en PDF, sur le canevas des documents de la plateforme (en-tête officiel, QR code de vérification).
 * Il atteste la participation de l'électeur sans jamais mentionner son choix (R43) : l'inscription ne connaît pas
 * le bulletin, et l'heure du vote n'est donnée qu'à l'heure près.
 */
class GenererAccuseDeVote
{
    public function __construct(private CodeQr $codeQr, private LienVerification $lien) {}

    /**
     * @throws InvalidArgumentException si l'électeur n'a pas voté
     */
    public function handle(Inscription $inscription): string
    {
        if (! $inscription->a_vote || $inscription->numero_accuse === null) {
            throw new InvalidArgumentException('Aucun vote n’est enregistré pour cette inscription.');
        }

        $inscription->loadMissing(['agent', 'structure', 'scrutin.phase.edition']);

        $html = view('vote.accuse-pdf', [
            'inscription' => $inscription,
            'scrutin' => $inscription->scrutin,
            'qr' => $this->codeQr->dataUri($this->lien->accuseDeVote($inscription)),
        ])->render();

        return Pdf::loadHTML($html)->setPaper('a4')->output();
    }

    public function nomDeFichier(Inscription $inscription): string
    {
        return 'accuse-de-vote-'.$inscription->numero_accuse.'.pdf';
    }
}
