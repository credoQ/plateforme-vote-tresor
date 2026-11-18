<?php

namespace App\Models;

use App\Enums\Acces\CodeRole;
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
#[Fillable(['name', 'login', 'email', 'password', 'actif', 'mot_de_passe_a_changer', 'derniere_connexion', 'agent_id', 'structure_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'actif' => true,
        'mot_de_passe_a_changer' => false,
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
            'password' => 'hashed',
            'actif' => 'boolean',
            'mot_de_passe_a_changer' => 'boolean',
            'derniere_connexion' => 'immutable_datetime',
        ];
    }
}
