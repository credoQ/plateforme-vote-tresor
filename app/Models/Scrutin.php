<?php

namespace App\Models;

use App\Enums\Editions\StatutPhase;
use App\Enums\Scrutins\StatutScrutin;
use App\Models\Concerns\IdentifieParUuid;
use Database\Factories\ScrutinFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Scrutin d’une phase (R19), issu d’un collège. Il relève d’au plus un pôle électoral : un collège de toute la DGTCP n’en a pas (R22).
 */
#[Table('scrutins')]
#[Fillable(['phase_id', 'college_id', 'pole_electoral_id', 'code', 'libelle', 'date_heure_ouverture', 'date_heure_fermeture', 'quorum', 'statut'])]
class Scrutin extends Model
{
    /** @use HasFactory<ScrutinFactory> */
    use HasFactory, IdentifieParUuid;

    /**
     * Fuseau des horaires saisis et affichés (heure du Bénin) ; ils sont enregistrés en UTC.
     */
    public const FUSEAU = 'Africa/Porto-Novo';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'statut' => 'EN_PREPARATION',
    ];

    /**
     * @return BelongsTo<Phase, $this>
     */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    /**
     * Collège dont relève le scrutin : il fixe l'origine des candidates et le nombre d'élues (R28, R52).
     *
     * @return BelongsTo<College, $this>
     */
    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }

    /**
     * Nombre de lauréates du scrutin, fixé par son collège (R52).
     */
    public function nbElues(): int
    {
        return $this->college->nb_elues;
    }

    /**
     * @return BelongsTo<PoleElectoral, $this>
     */
    public function poleElectoral(): BelongsTo
    {
        return $this->belongsTo(PoleElectoral::class);
    }

    /**
     * Circonscription du scrutin : les structures qu'il couvre (COUVRIR, R24).
     *
     * @return BelongsToMany<Structure, $this>
     */
    public function structures(): BelongsToMany
    {
        return $this->belongsToMany(Structure::class, 'couvertures')->withTimestamps();
    }

    /**
     * Liste électorale du scrutin (R24).
     *
     * @return HasMany<Inscription, $this>
     */
    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class);
    }

    /**
     * @return HasMany<Candidate, $this>
     */
    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    /**
     * @return HasMany<Bulletin, $this>
     */
    public function bulletins(): HasMany
    {
        return $this->hasMany(Bulletin::class);
    }

    /**
     * @return HasOne<ProcesVerbal, $this>
     */
    public function procesVerbal(): HasOne
    {
        return $this->hasOne(ProcesVerbal::class);
    }

    /**
     * Lauréates qualifiées pour la phase suivante, une fois les résultats publiés (R48, R52).
     *
     * @return HasMany<Candidate, $this>
     */
    public function laureates(): HasMany
    {
        return $this->candidates()->where('est_laureate', true)->orderBy('rang');
    }

    /**
     * Les listes électorales et les candidates ne changent plus une fois arrêtées (R27, R30) ;
     * seules la désactivation et la réactivation restent possibles jusqu'à l'ouverture (R33, R34).
     */
    public function listesSontModifiables(): bool
    {
        return $this->statut === StatutScrutin::EnPreparation;
    }

    /**
     * R27 : le comité peut rouvrir, par décision motivée, des listes arrêtées tant que le scrutin n'est pas ouvert.
     */
    public function listesPeuventEtreRouvertes(): bool
    {
        return $this->statut === StatutScrutin::ListesArretees;
    }

    /**
     * Participation, seule donnée visible pendant le vote (R46).
     *
     * @return array{inscrits: int, votants: int, taux: float}
     */
    public function participation(): array
    {
        $inscrits = $this->inscriptions()->count();
        $votants = $this->inscriptions()->where('a_vote', true)->count();

        return [
            'inscrits' => $inscrits,
            'votants' => $votants,
            'taux' => $inscrits === 0 ? 0.0 : round($votants * 100 / $inscrits, 2),
        ];
    }

    /**
     * Le scrutin est encore ouvert alors que sa date-heure de fermeture est passée : le comité peut le fermer.
     */
    public function fermetureDepassee(): bool
    {
        return $this->statut === StatutScrutin::Ouvert && $this->date_heure_fermeture?->isPast();
    }

    public function accepteLesDesistements(): bool
    {
        return in_array($this->statut, [StatutScrutin::EnPreparation, StatutScrutin::ListesArretees], true);
    }

    /**
     * Horaires encore modifiables (R19) : le scrutin n'est pas ouvert et sa phase non plus. À l'ouverture de la
     * phase, les horaires de tous ses scrutins sont figés ; seule la prolongation reste possible ensuite.
     */
    public function horairesModifiables(): bool
    {
        return $this->accepteLesDesistements() && $this->phase?->statut === StatutPhase::Planifiee;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_heure_ouverture' => 'immutable_datetime',
            'date_heure_fermeture' => 'immutable_datetime',
            'quorum' => 'decimal:2',
            'statut' => StatutScrutin::class,
        ];
    }
}
