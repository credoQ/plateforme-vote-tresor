<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\AffectationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Affectation d'un agent à une structure sur une période (association AFFECTER, R12).
 * Une date de fin vide signifie que l'affectation est toujours en cours.
 */
#[Table('affectations')]
#[Fillable(['agent_id', 'structure_id', 'date_debut', 'date_fin'])]
class Affectation extends Model
{
    /** @use HasFactory<AffectationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * @return BelongsTo<Structure, $this>
     */
    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    /**
     * Durée de l'affectation en années et mois (« 8 ans 3 mois »), jusqu'à aujourd'hui si elle est en cours.
     */
    public function duree(): string
    {
        $intervalle = $this->date_debut->diff(($this->date_fin ?? now())->copy()->addDay());
        $parties = array_filter([
            $intervalle->y > 0 ? $intervalle->y.' an'.($intervalle->y > 1 ? 's' : '') : null,
            $intervalle->m > 0 ? $intervalle->m.' mois' : null,
        ]);

        return $parties === [] ? 'Moins d’un mois' : implode(' ', $parties);
    }

    /**
     * Affectations qui couvrent la date donnée, bornes incluses (aujourd'hui par défaut).
     */
    #[Scope]
    protected function enCoursLe(Builder $query, ?CarbonInterface $date = null): Builder
    {
        $jour = ($date ?? Carbon::today())->toDateString();

        return $query->whereDate('date_debut', '<=', $jour)
            ->where(fn (Builder $q) => $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $jour));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
        ];
    }
}
