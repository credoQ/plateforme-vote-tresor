<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Procès-verbal d'un scrutin (R47, R51). Numéroté à la validation, archivé à la publication (R55).
 */
#[Table('proces_verbaux')]
#[Fillable([
    'scrutin_id', 'numero', 'date_etablissement', 'nb_inscrits', 'nb_votants', 'nb_blancs', 'nb_exprimes',
    'taux_participation', 'quorum_atteint', 'egalite_a_departager', 'motif_departage', 'empreinte_chaine',
    'fichier', 'valide_par_id', 'date_validation', 'date_publication',
])]
class ProcesVerbal extends Model
{
    /**
     * @return BelongsTo<Scrutin, $this>
     */
    public function scrutin(): BelongsTo
    {
        return $this->belongsTo(Scrutin::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_etablissement' => 'immutable_datetime',
            'nb_inscrits' => 'integer',
            'nb_votants' => 'integer',
            'nb_blancs' => 'integer',
            'nb_exprimes' => 'integer',
            'taux_participation' => 'decimal:2',
            'quorum_atteint' => 'boolean',
            'egalite_a_departager' => 'boolean',
            'date_validation' => 'immutable_datetime',
            'date_publication' => 'immutable_datetime',
        ];
    }
}
