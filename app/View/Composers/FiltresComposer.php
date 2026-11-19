<?php

namespace App\View\Composers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Données de la barre de filtres (components.barre-filtres) : un filtre est-il appliqué, et l'adresse
 * de la liste sans filtre pour le lien « Réinitialiser ». Le numéro de page n'est pas un filtre.
 */
class FiltresComposer
{
    public function __construct(private Request $request) {}

    public function compose(View $view): void
    {
        $filtres = collect($this->request->query())->except('page')->filter(fn (mixed $valeur): bool => filled($valeur));

        $view->with([
            'filtresActifs' => $filtres->isNotEmpty(),
            'adresseSansFiltre' => $this->request->url(),
        ]);
    }
}
