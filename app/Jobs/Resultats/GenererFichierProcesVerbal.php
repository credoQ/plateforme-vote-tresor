<?php

namespace App\Jobs\Resultats;

use App\Actions\Documents\CodeQr;
use App\Actions\Documents\LienVerification;
use App\Models\ProcesVerbal;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Génère le procès-verbal PDF d'un scrutin validé (R51), avec le QR code de vérification en ligne.
 * Le procès-verbal publié n'est plus modifiable (R55) : le fichier n'est produit qu'avant la publication.
 */
class GenererFichierProcesVerbal implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $procesVerbalId) {}

    public function uniqueId(): string
    {
        return (string) $this->procesVerbalId;
    }

    public function handle(CodeQr $codeQr, LienVerification $lien): void
    {
        $pv = ProcesVerbal::with([
            'validePar',
            'scrutin.phase.edition',
            'scrutin.candidates' => fn ($query) => $query->with('agent')->orderByRaw('rang nulls last')->orderBy('id'),
        ])->findOrFail($this->procesVerbalId);

        if ($pv->date_publication !== null || $pv->numero === null) {
            return;
        }

        $html = view('resultats.proces-verbal', [
            'pv' => $pv,
            'scrutin' => $pv->scrutin,
            'edition' => $pv->scrutin->phase->edition,
            'qr' => $codeQr->dataUri($lien->procesVerbal($pv)),
        ])->render();
        $chemin = "proces-verbaux/{$pv->numero}.pdf";

        Storage::disk('local')->put($chemin, Pdf::loadHTML($html)->setPaper('a4')->output());

        $pv->update(['fichier' => $chemin]);
    }
}
