<?php

namespace App\Actions\Agents;

use App\Actions\Acces\OuvrirCompteElecteur;
use App\Enums\Agents\Sexe;
use App\Models\Agent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Crée ou met à jour la fiche d'un agent (R11) : matricule unique, NPI unique, nom, prénoms, sexe et adresse e-mail
 * obligatoires (l'adresse reçoit les codes de connexion, R42).
 * Les champs informatifs sont enregistrés tels quels, sans contrôle métier.
 * À la création, l'agent reçoit son compte électeur, identifié par son matricule (R3).
 */
class EnregistrerAgent
{
    public function __construct(private OuvrirCompteElecteur $ouvrirCompte) {}

    /**
     * @param  array{matricule: string, npi?: string|null, nom: string, prenoms: string, sexe: Sexe|string, date_naissance?: string|null, date_prise_service?: string|null, telephone?: string|null, email?: string|null, fonction?: string|null, statut?: string|null}  $donnees
     *
     * @throws ValidationException
     */
    public function handle(array $donnees, ?Agent $agent = null): Agent
    {
        if (($donnees['sexe'] ?? null) instanceof Sexe) {
            $donnees['sexe'] = $donnees['sexe']->value;
        }

        $donnees = Validator::make($donnees, [
            'matricule' => ['required', 'string', 'max:30', Rule::unique('agents', 'matricule')->ignore($agent?->id)],
            'npi' => ['nullable', 'string', 'max:20', Rule::unique('agents', 'npi')->ignore($agent?->id)],
            'nom' => ['required', 'string', 'max:100'],
            'prenoms' => ['required', 'string', 'max:150'],
            'sexe' => ['required', Rule::enum(Sexe::class)],
            'date_naissance' => ['nullable', 'date', 'before:today'],
            'date_prise_service' => ['nullable', 'date'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
            'fonction' => ['nullable', 'string', 'max:150'],
            'statut' => ['nullable', 'string', 'max:60'],
        ], [
            'matricule.unique' => 'Ce matricule est déjà attribué à un autre agent.',
            'npi.unique' => 'Ce NPI est déjà attribué à un autre agent.',
            'sexe.required' => 'Le sexe de l’agent est obligatoire : il détermine la qualité de candidate.',
            'sexe.enum' => 'Le sexe doit être F (féminin) ou M (masculin).',
            'email.required' => 'L’adresse e-mail de l’agent est obligatoire : elle reçoit les codes de connexion.',
            'email.email' => 'Adresse e-mail invalide.',
            'required' => 'Le champ :attribute est obligatoire.',
            'date' => 'Le champ :attribute doit être une date valide.',
        ])->validate();

        if ($agent !== null) {
            $agent->fill($donnees)->save();

            return $agent;
        }

        // Un nouvel agent reçoit aussitôt son compte électeur (R3) : la fiche et le compte sont créés ensemble, ou pas du tout.
        return DB::transaction(function () use ($donnees): Agent {
            $agent = Agent::create($donnees);
            $this->ouvrirCompte->handle($agent);

            return $agent;
        });
    }
}
