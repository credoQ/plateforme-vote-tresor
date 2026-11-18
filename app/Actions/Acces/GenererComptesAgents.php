<?php

namespace App\Actions\Acces;

use App\Enums\Acces\CodeRole;
use App\Models\Agent;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Crée un compte électeur pour chaque agent en service qui n'en a pas (R3, R13) : identifiant = matricule,
 * mot de passe provisoire à remettre par le point focal et à changer à la première connexion.
 */
class GenererComptesAgents
{
    public function __construct(private MotDePasseProvisoire $motDePasse) {}

    /**
     * @return list<array{matricule: string, nom: string, structure: string, mot_de_passe: string}>
     */
    public function handle(?CarbonInterface $date = null): array
    {
        $roleElecteur = Role::where('code', CodeRole::Electeur->value)->firstOrFail();
        $comptes = [];

        Agent::enServiceLe($date)
            ->whereDoesntHave('compte')
            ->whereNotIn('matricule', User::select('login'))
            ->chunkById(200, function ($agents) use ($roleElecteur, $date, &$comptes): void {
                DB::transaction(function () use ($agents, $roleElecteur, $date, &$comptes): void {
                    foreach ($agents as $agent) {
                        $motDePasse = $this->motDePasse->generer();
                        $utilisateur = User::create([
                            'name' => $agent->nomComplet(),
                            'login' => $agent->matricule,
                            'email' => null,
                            'password' => $motDePasse,
                            'mot_de_passe_a_changer' => true,
                            'agent_id' => $agent->id,
                        ]);
                        $utilisateur->roles()->attach($roleElecteur);

                        $comptes[] = [
                            'matricule' => $agent->matricule,
                            'nom' => $agent->nomComplet(),
                            'structure' => $agent->affectationLe($date)?->structure->code ?? '',
                            'mot_de_passe' => $motDePasse,
                        ];
                    }
                });
            });

        return $comptes;
    }
}
