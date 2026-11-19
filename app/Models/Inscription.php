<?php

namespace App\Models;

use Database\Factories\InscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inscription d'un agent sur la liste électorale d'un scrutin (INSCRIRE, R24 à R27), qui porte son émargement.
 * Elle n'a aucun lien avec les bulletins (R43).
 */
#[Table('inscriptions')]
#[Fillable(['scrutin_id', 'phase_id', 'agent_id', 'structure_id', 'a_vote', 'date_heure_vote', 'canal', 'numero_accuse'])]
class Inscription extends Model
{
    /** @use HasFactory<InscriptionFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'a_vote' => false,
    ];

    /**
     * @return BelongsTo<Scrutin, $this>
     */
    public function scrutin(): BelongsTo
    {
        return $this->belongsTo(Scrutin::class);
    }

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Structure d'affectation de l'agent à la date de gel (R15) : sert au contrôle par le point focal (R4).
     *
     * @return BelongsTo<Structure, $this>
     */
    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    /**
     * Créneau du vote, à l'heure près comme son horodatage (R43) : « 19/11/2026, entre 10 h et 11 h ».
     */
    public function creneauDeVote(): ?string
    {
        if ($this->date_heure_vote === null) {
            return null;
        }

        $debut = $this->date_heure_vote->timezone('Africa/Porto-Novo');

        return $debut->format('d/m/Y').', entre '.$debut->format('G').' h et '.$debut->addHour()->format('G').' h';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'a_vote' => 'boolean',
            'date_heure_vote' => 'immutable_datetime',
        ];
    }
}
