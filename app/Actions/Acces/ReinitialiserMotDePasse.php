<?php

namespace App\Actions\Acces;

use App\Actions\Audit\Journaliser;
use App\Enums\Audit\ActionAuditee;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Réinitialise le mot de passe d'un utilisateur : un mot de passe provisoire est généré, à remettre
 * à l'intéressé et à changer à la prochaine connexion. Ses sessions « se souvenir de moi » sont invalidées.
 */
class ReinitialiserMotDePasse
{
    public function __construct(private MotDePasseProvisoire $motDePasse, private Journaliser $journal) {}

    public function handle(User $utilisateur): string
    {
        $motDePasse = $this->motDePasse->generer();

        DB::transaction(function () use ($utilisateur, $motDePasse): void {
            $utilisateur->forceFill([
                'password' => $motDePasse,
                'mot_de_passe_a_changer' => true,
                'remember_token' => null,
            ])->save();

            $this->journal->handle(ActionAuditee::MotDePasseReinitialise, $utilisateur, ['login' => $utilisateur->login]);
        });

        return $motDePasse;
    }
}
