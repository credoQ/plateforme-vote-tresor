<?php

namespace App\Models;

use App\Enums\Editions\StatutEdition;
use App\Enums\Scrutins\StatutScrutin;
use Database\Factories\EditionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Édition annuelle du concours (R16). Le vote blanc et le quorum sont des paramètres de l'édition,
 * en attendant leur validation par le métier.
 */
#[Table('editions')]
#[Fillable(['annee', 'libelle', 'date_debut', 'date_fin', 'date_gel', 'statut', 'vote_blanc_autorise', 'vote_pour_soi_autorise', 'quorum'])]
class Edition extends Model
{
    /** @use HasFactory<EditionFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'statut' => 'EN_PREPARATION',
        'vote_blanc_autorise' => false,
        'vote_pour_soi_autorise' => true,
    ];

    /**
     * Les trois phases, dans l'ordre (R17).
     *
     * @return HasMany<Phase, $this>
     */
    public function phases(): HasMany
    {
        return $this->hasMany(Phase::class)->orderBy('numero');
    }

    /**
     * @return HasManyThrough<Scrutin, Phase, $this>
     */
    public function scrutins(): HasManyThrough
    {
        return $this->hasManyThrough(Scrutin::class, Phase::class);
    }

    public function phase(int $numero): ?Phase
    {
        return $this->phases->firstWhere('numero', $numero);
    }

    /**
     * La Femme modèle du Trésor de l'édition : la lauréate de la finale, une fois ses résultats publiés (R53).
     */
    public function femmeModele(): ?Candidate
    {
        return Candidate::query()
            ->with('agent')
            ->where('est_laureate', true)
            ->whereHas('scrutin', fn (Builder $scrutin) => $scrutin
                ->where('statut', StatutScrutin::ResultatsPublies->value)
                ->whereHas('phase', fn (Builder $phase) => $phase->where('edition_id', $this->id)->where('numero', Phase::FINALE)))
            ->orderBy('rang')
            ->first();
    }

    public function estModifiable(): bool
    {
        return $this->statut === StatutEdition::EnPreparation;
    }

    #[Scope]
    protected function enCours(Builder $query): Builder
    {
        return $query->where('statut', StatutEdition::EnCours->value);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'annee' => 'integer',
            'date_debut' => 'date',
            'date_fin' => 'date',
            'date_gel' => 'date',
            'statut' => StatutEdition::class,
            'vote_blanc_autorise' => 'boolean',
            'vote_pour_soi_autorise' => 'boolean',
            'quorum' => 'decimal:2',
        ];
    }
}
