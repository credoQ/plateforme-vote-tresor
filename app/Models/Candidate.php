<?php

namespace App\Models;

use App\Enums\Scrutins\OrigineCandidate;
use App\Enums\Scrutins\StatutCandidate;
use App\Models\Concerns\IdentifieParUuid;
use Database\Factories\CandidateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Candidate d'un scrutin (R31) : une agent, un scrutin, un statut et son résultat.
 */
#[Table('candidates')]
#[Fillable([
    'scrutin_id', 'agent_id', 'origine', 'reference_nomination', 'nommee_par_id', 'date_nomination', 'statut', 'date_desistement', 'motif_desistement',
    'desactivee_par_id', 'candidate_source_id', 'nb_voix', 'pourcentage', 'rang', 'est_laureate',
])]
class Candidate extends Model
{
    /** @use HasFactory<CandidateFactory> */
    use HasFactory, IdentifieParUuid;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'statut' => 'ACTIVE',
        'est_laureate' => false,
    ];

    /**
     * @return BelongsTo<Scrutin, $this>
     */
    public function scrutin(): BelongsTo
    {
        return $this->belongsTo(Scrutin::class);
    }

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function desactiveePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'desactivee_par_id');
    }

    /**
     * Membre du comité qui a saisi la nomination (R58).
     *
     * @return BelongsTo<User, $this>
     */
    public function nommeePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nommee_par_id');
    }

    /**
     * Candidature de la phase précédente dont celle-ci est issue (QUALIFIER, R35).
     *
     * @return BelongsTo<Candidate, $this>
     */
    public function candidateSource(): BelongsTo
    {
        return $this->belongsTo(self::class, 'candidate_source_id');
    }

    /**
     * @return HasOne<Candidate, $this>
     */
    public function qualification(): HasOne
    {
        return $this->hasOne(self::class, 'candidate_source_id');
    }

    public function estActive(): bool
    {
        return $this->statut === StatutCandidate::Active;
    }

    /**
     * Candidates actives : seules elles figurent sur la liste et peuvent recevoir des voix (R33).
     */
    #[Scope]
    protected function actives(Builder $query): Builder
    {
        return $query->where('statut', StatutCandidate::Active->value);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origine' => OrigineCandidate::class,
            'statut' => StatutCandidate::class,
            'date_desistement' => 'immutable_datetime',
            'date_nomination' => 'immutable_datetime',
            'nb_voix' => 'integer',
            'pourcentage' => 'decimal:2',
            'rang' => 'integer',
            'est_laureate' => 'boolean',
        ];
    }
}
