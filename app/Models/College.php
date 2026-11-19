<?php

namespace App\Models;

use App\Enums\Editions\Decoupage;
use App\Enums\Scrutins\OrigineCandidate;
use Database\Factories\CollegeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Collège d'une phase (R20 à R23) : un pôle électoral, ou l'ensemble de la DGTCP quand il n'a pas de pôle.
 * Il fixe le découpage en scrutins, l'origine des candidates (R28) et le nombre d'élues par scrutin (R52).
 * Paramétré à la création de l'édition, il est figé dès que des scrutins sont générés.
 */
#[Table('colleges')]
#[Fillable(['phase_id', 'pole_electoral_id', 'decoupage', 'origine_candidates', 'nb_elues'])]
class College extends Model
{
    /** @use HasFactory<CollegeFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Phase, $this>
     */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    /**
     * @return BelongsTo<PoleElectoral, $this>
     */
    public function poleElectoral(): BelongsTo
    {
        return $this->belongsTo(PoleElectoral::class);
    }

    /**
     * @return HasMany<Scrutin, $this>
     */
    public function scrutins(): HasMany
    {
        return $this->hasMany(Scrutin::class);
    }

    public function libelle(): string
    {
        return $this->poleElectoral?->code->libelle() ?? 'Ensemble de la DGTCP';
    }

    /**
     * Résumé du paramétrage, affiché sur la fiche de l'édition.
     */
    public function resume(): string
    {
        return "{$this->libelle()} · {$this->decoupage->libelle()} · {$this->origine_candidates->libelleCollege()} · {$this->nb_elues} élue(s) par scrutin";
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decoupage' => Decoupage::class,
            'origine_candidates' => OrigineCandidate::class,
            'nb_elues' => 'integer',
        ];
    }
}
