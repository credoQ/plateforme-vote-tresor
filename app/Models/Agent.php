<?php

namespace App\Models;

use App\Enums\Agents\Sexe;
use App\Models\Concerns\IdentifieParUuid;
use Carbon\CarbonInterface;
use Database\Factories\AgentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Agent de la DGTCP (R11). La date de prise de service, le téléphone, l'e-mail, la fonction
 * et le statut sont informatifs : aucune règle ne s'appuie sur eux.
 */
#[Table('agents')]
#[Fillable([
    'matricule', 'npi', 'nom', 'prenoms', 'sexe', 'date_naissance',
    'date_prise_service', 'telephone', 'email', 'fonction', 'statut',
])]
class Agent extends Model
{
    /** @use HasFactory<AgentFactory> */
    use HasFactory, IdentifieParUuid;

    /**
     * Historique des affectations, de la plus récente à la plus ancienne (R12).
     *
     * @return HasMany<Affectation, $this>
     */
    public function affectations(): HasMany
    {
        return $this->hasMany(Affectation::class)->orderByDesc('date_debut');
    }

    /**
     * Affectation en cours à la date donnée (aujourd'hui par défaut), s'il y en a une (R12).
     */
    public function affectationLe(?CarbonInterface $date = null): ?Affectation
    {
        return $this->affectations()->enCoursLe($date)->with('structure')->first();
    }

    /**
     * Affectation en cours aujourd'hui, à charger avec la liste des agents (R12, R13).
     *
     * @return HasOne<Affectation, $this>
     */
    public function affectationEnCours(): HasOne
    {
        return $this->hasOne(Affectation::class)->enCoursLe();
    }

    /**
     * Compte utilisateur de l'agent, au plus un (R3).
     *
     * @return HasOne<User, $this>
     */
    public function compte(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * Adresse des notifications : celle du compte, sinon celle de la fiche agent ; null si aucune n'est valide.
     */
    public function adresseDeNotification(): ?string
    {
        $email = $this->compte?->email ?: $this->email;

        return filled($email) && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    public function estUneFemme(): bool
    {
        return $this->sexe === Sexe::Feminin;
    }

    public function nomComplet(): string
    {
        return trim($this->nom.' '.$this->prenoms);
    }

    /**
     * Initiales du nom et du premier prénom, pour l'avatar de la fiche.
     */
    public function initiales(): string
    {
        return mb_strtoupper(mb_substr($this->nom, 0, 1).mb_substr($this->prenoms ?? '', 0, 1));
    }

    /**
     * Variante de l'avatar dessiné d'une candidate sans photo (components/avatar-femme) : tirée du matricule,
     * elle reste la même pour l'agent d'un scrutin à l'autre.
     *
     * @return array{tenue: int, peau: int, cheveux: int, coiffure: int}
     */
    public function avatar(): array
    {
        $empreinte = crc32((string) ($this->matricule ?? $this->id));

        return [
            'tenue' => $empreinte % 5 + 1,
            'peau' => ($empreinte >> 3) % 4 + 1,
            'cheveux' => ($empreinte >> 6) % 3 + 1,
            'coiffure' => ($empreinte >> 8) % 4 + 1,
        ];
    }

    /**
     * Agents en service à la date donnée : une affectation en cours dans une structure active (R13).
     */
    #[Scope]
    protected function enServiceLe(Builder $query, ?CarbonInterface $date = null): Builder
    {
        return $query->whereHas('affectations', fn (Builder $affectations) => $affectations
            ->enCoursLe($date)
            ->whereHas('structure', fn (Builder $structure) => $structure->actives()));
    }

    /**
     * Agents dont l'affectation en cours à la date donnée est l'une des structures indiquées (R24, R25).
     *
     * @param  iterable<int>  $structureIds
     */
    #[Scope]
    protected function affectesLe(Builder $query, CarbonInterface $date, iterable $structureIds): Builder
    {
        return $query->whereHas('affectations', fn (Builder $affectations) => $affectations
            ->enCoursLe($date)
            ->whereIn('structure_id', $structureIds));
    }

    /**
     * Agents de sexe féminin, candidates potentielles de la phase 1.
     */
    #[Scope]
    protected function femmes(Builder $query): Builder
    {
        return $query->where('sexe', Sexe::Feminin->value);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sexe' => Sexe::class,
            'date_naissance' => 'date',
            'date_prise_service' => 'date',
        ];
    }
}
