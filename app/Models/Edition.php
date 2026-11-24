<?php

namespace App\Models;

use App\Enums\Editions\StatutEdition;
use App\Enums\Scrutins\StatutScrutin;
use App\Models\Concerns\IdentifieParUuid;
use Carbon\CarbonInterface;
use Database\Factories\EditionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Édition annuelle du concours (R16). Le vote blanc et le quorum sont des paramètres de l'édition,
 * en attendant leur validation par le métier.
 */
#[Table('editions')]
#[Fillable(['annee', 'libelle', 'date_debut', 'date_fin', 'date_gel', 'statut', 'vote_blanc_autorise', 'vote_pour_soi_autorise', 'quorum', 'delai_reeligibilite'])]
class Edition extends Model
{
    /** @use HasFactory<EditionFactory> */
    use HasFactory, IdentifieParUuid;

    public const CLE_CACHE_EN_COURS = 'edition.en-cours';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'statut' => 'EN_PREPARATION',
        'vote_blanc_autorise' => false,
        'vote_pour_soi_autorise' => true,
        'delai_reeligibilite' => 3,
    ];

    /**
     * Les phases, de une à trois, dans l'ordre (R17).
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
     * Les femmes modèles du Trésor de l'édition : les lauréates de la dernière phase, une fois leurs résultats
     * publiés (R53). Il y en a une par élue de chaque scrutin de cette phase.
     *
     * @return Collection<int, Candidate>
     */
    public function femmesModeles(): Collection
    {
        $derniere = $this->phases()->reorder('numero', 'desc')->value('id');

        return Candidate::query()
            ->with(['agent', 'scrutin'])
            ->where('est_laureate', true)
            ->whereHas('scrutin', fn (Builder $scrutin) => $scrutin
                ->where('phase_id', $derniere)
                ->where('statut', StatutScrutin::ResultatsPublies->value))
            ->get()
            ->sortBy(fn (Candidate $candidate): string => $candidate->scrutin->code.str_pad((string) $candidate->rang, 3, '0', STR_PAD_LEFT))
            ->values();
    }

    /**
     * Agents inéligibles à cette édition (R59) : élues femmes modèles depuis moins de « délai » ans, soit
     * année de l'édition − année d'élection ≤ délai. Matricule => année de la dernière élection.
     *
     * @return SupportCollection<string, int>
     */
    public function ineligibles(): SupportCollection
    {
        return FemmeModele::query()
            ->where('annee', '<', $this->annee)
            ->where('annee', '>=', $this->annee - $this->delai_reeligibilite)
            ->orderBy('annee')
            ->pluck('annee', 'matricule');
    }

    /**
     * Édition qui s'organise ou se déroule (la plus récente en préparation ou en cours) : celle dont le délai
     * de rééligibilité s'applique aujourd'hui (R59).
     */
    /**
     * Année de l'édition en cours, lue à chaque page (en-tête de l'espace électeur, connexion, e-mails) : gardée en
     * cache cinq minutes, et oubliée dès qu'une édition est enregistrée ou supprimée. memo() évite de relire le cache
     * plusieurs fois dans une même requête. Un tableau est mis en cache pour qu'une absence d'édition le soit aussi.
     */
    public static function anneeEnCours(): ?int
    {
        return Cache::memo()->remember(self::CLE_CACHE_EN_COURS, now()->addMinutes(5), fn (): array => [
            'annee' => self::query()->enCours()->value('annee'),
        ])['annee'];
    }

    public static function oublierEnCours(): void
    {
        Cache::memo()->forget(self::CLE_CACHE_EN_COURS);
    }

    protected static function booted(): void
    {
        // Oubli immédiat, puis après la validation de la transaction : une lecture concurrente ne garde pas l'ancienne valeur.
        $oublier = function (): void {
            self::oublierEnCours();
            DB::afterCommit(fn () => self::oublierEnCours());
        };

        static::saved($oublier);
        static::deleted($oublier);
    }

    public static function courante(): ?self
    {
        return self::whereIn('statut', [StatutEdition::EnPreparation->value, StatutEdition::EnCours->value])->orderByDesc('annee')->first();
    }

    /**
     * Dernière année où une femme modèle élue en $annee est inéligible, selon le délai de cette édition (R59).
     */
    public function derniereAnneeIneligible(int $annee): int
    {
        return $annee + $this->delai_reeligibilite;
    }

    /**
     * Motif d'inéligibilité d'une agent à cette édition (R59), ou null si elle peut être candidate.
     */
    public function motifIneligibilite(string $matricule): ?string
    {
        $annee = $this->ineligibles()->get($matricule);

        return $annee === null ? null : "élue Femme modèle du Trésor en {$annee}, elle ne peut être de nouveau candidate qu’à partir de ".($this->derniereAnneeIneligible($annee) + 1);
    }

    public function estModifiable(): bool
    {
        return $this->statut === StatutEdition::EnPreparation;
    }

    /**
     * Les phases et leurs collèges se paramètrent tant que l'édition est en préparation et qu'aucun scrutin n'est généré.
     */
    public function organisationEstModifiable(): bool
    {
        return $this->estModifiable() && ! $this->scrutins()->exists();
    }

    /**
     * Les listes de l'édition sont gelées dès le premier arrêt des listes d'une phase (R14, R16).
     */
    public function listesSontGelees(): bool
    {
        return $this->date_gel !== null;
    }

    /**
     * Date à laquelle se lisent les affectations pour les listes et les candidates (R14, R15, R29) : la date de gel,
     * ou, tant que les listes ne sont pas gelées, le jour même (listes provisoires).
     */
    public function dateDeReference(): CarbonInterface
    {
        return $this->date_gel ?? Carbon::today();
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
            'delai_reeligibilite' => 'integer',
        ];
    }
}
