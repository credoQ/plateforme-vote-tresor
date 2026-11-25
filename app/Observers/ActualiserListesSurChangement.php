<?php

namespace App\Observers;

use App\Jobs\Scrutins\ActualiserListesElectorales;
use Illuminate\Database\Eloquent\Model;

/**
 * Un agent, une affectation ou une structure change : les listes électorales encore modifiables se mettent à jour
 * d'elles-mêmes (ActualiserListesElectorales, après la validation de la transaction).
 */
class ActualiserListesSurChangement
{
    public function saved(Model $modele): void
    {
        ActualiserListesElectorales::dispatch();
    }

    public function deleted(Model $modele): void
    {
        ActualiserListesElectorales::dispatch();
    }
}
