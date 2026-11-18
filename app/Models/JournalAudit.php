<?php

namespace App\Models;

use App\Enums\Audit\ActionAuditee;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Entrée du journal d'audit (R56), en ajout seul.
 */
#[Table('journal_audit')]
#[Fillable(['date_heure', 'user_id', 'login', 'action', 'entite_type', 'entite_id', 'details', 'adresse_ip'])]
class JournalAudit extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Le journal d’audit est en ajout seul (R56).'));
        static::deleting(fn () => throw new LogicException('Le journal d’audit est en ajout seul (R56).'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_heure' => 'immutable_datetime',
            'action' => ActionAuditee::class,
            'details' => 'array',
        ];
    }
}
