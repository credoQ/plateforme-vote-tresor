<?php

namespace App\Models;

use App\Enums\Acces\CodeRole;
use App\Models\Concerns\IdentifieParUuid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Compte utilisateur (R1 à R4). Un agent a au plus un compte ; un compte peut n'être lié à aucun agent.
 */
#[Fillable(['name', 'login', 'email', 'actif', 'derniere_connexion', 'agent_id', 'structure_id'])]
#[Hidden(['password', 'remember_token', 'code_acces'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, IdentifieParUuid, Notifiable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'actif' => true,
    ];

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_utilisateur');
    }

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Structure de rattachement du point focal (R4).
     *
     * @return BelongsTo<Structure, $this>
     */
    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    /**
     * Adresse où envoyer le code de connexion : celle du compte, sinon celle de la fiche agent (R42).
     */
    public function adresseDeConnexion(): ?string
    {
        $email = $this->email ?: $this->agent?->email;

        return filled($email) && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * Initiales affichées dans l'avatar : les deux premiers mots du nom.
     */
    public function initiales(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))->take(2)->map(fn (string $mot): string => mb_substr($mot, 0, 1))->implode('');
    }

    /**
     * Le compte a un profil de gestion (administrateur, comité, point focal, observateur) : il arrive sur la console
     * de gestion ; un électeur seul n'a que l'espace électeur.
     */
    public function aAccesALaGestion(): bool
    {
        return $this->aLeRole(CodeRole::Administrateur, CodeRole::Comite, CodeRole::PointFocal, CodeRole::Observateur);
    }

    /**
     * Page d'arrivée après la connexion : la console de gestion pour un profil de gestion, sinon l'espace électeur.
     */
    public function pageDAccueil(): string
    {
        return $this->aAccesALaGestion() ? route('tableau-de-bord') : route('espace');
    }

    public function aLeRole(CodeRole ...$roles): bool
    {
        $codes = array_map(fn (CodeRole $role): string => $role->value, $roles);

        return $this->roles->contains(fn (Role $role): bool => in_array($role->code->value, $codes, true));
    }

    /**
     * Structures sur lesquelles le point focal intervient : sa structure et celles qui lui sont rattachées,
     * à tous les niveaux (services, Trésoreries communales d'une TD) (R4).
     *
     * @return list<int>
     */
    public function perimetre(): array
    {
        if ($this->structure_id === null) {
            return [];
        }

        $perimetre = [$this->structure_id];
        $niveau = [$this->structure_id];
        while ($niveau !== []) {
            $niveau = Structure::whereIn('structure_parente_id', $niveau)->whereNotIn('id', $perimetre)->pluck('id')->all();
            $perimetre = [...$perimetre, ...$niveau];
        }

        return $perimetre;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            // Code personnel du mode « code imprimé », chiffré avec la clé de l'application (ModeConnexion).
            'code_acces' => 'encrypted',
            'code_acces_genere_le' => 'datetime',
            'actif' => 'boolean',
            'derniere_connexion' => 'immutable_datetime',
        ];
    }
}
