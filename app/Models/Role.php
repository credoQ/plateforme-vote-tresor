<?php

namespace App\Models;

use App\Enums\Acces\CodeRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Rôle d'un utilisateur (R2).
 */
#[Table('roles')]
#[Fillable(['code', 'libelle'])]
class Role extends Model
{
    /**
     * @return BelongsToMany<User, $this>
     */
    public function utilisateurs(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_utilisateur');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'code' => CodeRole::class,
        ];
    }
}
