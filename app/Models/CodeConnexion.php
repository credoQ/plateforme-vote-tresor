<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Code de connexion à usage unique envoyé par e-mail (R42).
 */
#[Table('codes_connexion')]
#[Fillable(['user_id', 'empreinte', 'expire_le', 'tentatives', 'utilise_le', 'adresse_ip'])]
class CodeConnexion extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function estValide(): bool
    {
        return $this->utilise_le === null && $this->expire_le->isFuture() && $this->tentatives < self::tentativesMax();
    }

    public static function tentativesMax(): int
    {
        return 5;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expire_le' => 'immutable_datetime',
            'utilise_le' => 'immutable_datetime',
            'tentatives' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
