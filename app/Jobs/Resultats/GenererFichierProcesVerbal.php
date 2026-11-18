<?php

namespace App\Jobs\Resultats;

use App\Models\ProcesVerbal;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Génère le fichier du procès-verbal d'un scrutin validé (R51), en HTML imprimable.
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

    public function handle(): void
    {
        $pv = ProcesVerbal::with([
            'scrutin.phase.edition',
            'scrutin.candidates' => fn ($query) => $query->with('agent')->orderByRaw('rang nulls last')->orderBy('id'),
        ])->findOrFail($this->procesVerbalId);

        if ($pv->date_publication !== null || $pv->numero === null) {
            return;
        }

        $chemin = "proces-verbaux/{$pv->numero}.html";
        Storage::disk('local')->put($chemin, view('resultats.proces-verbal', ['pv' => $pv])->render());

        $pv->update(['fichier' => $chemin]);
    }
}
