<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Bulletin de vote (R41, R43, R44).
 *
 * Il ne porte aucune relation vers un agent ou un utilisateur. Son identifiant est aléatoire, il n'a pas
 * de created_at et son horodatage est tronqué à l'heure. Il n'est jamais modifié ni supprimé.
 */
#[Table('bulletins')]
#[Fillable(['scrutin_id', 'candidate_id', 'est_blanc', 'date_heure', 'empreinte', 'empreinte_precedente'])]
class Bulletin extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Un bulletin ne peut pas être modifié (R44).'));
        static::deleting(fn () => throw new LogicException('Un bulletin ne peut pas être supprimé (R44).'));
    }

    /**
     * Identifiant aléatoire (UUID v4) : l'ordre des identifiants ne révèle pas l'ordre des votes.
     */
    public function newUniqueId(): string
    {
        return (string) str()->uuid();
    }

    /**
     * @return BelongsTo<Scrutin, $this>
     */
    public function scrutin(): BelongsTo
    {
        return $this->belongsTo(Scrutin::class);
    }

    /**
     * @return BelongsTo<Candidate, $this>
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'est_blanc' => 'boolean',
            'date_heure' => 'immutable_datetime',
        ];
    }
}
