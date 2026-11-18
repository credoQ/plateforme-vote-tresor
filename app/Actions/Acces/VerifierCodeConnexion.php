<?php

namespace App\Actions\Acces;

use App\Models\CodeConnexion;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Vérifie le code de connexion saisi (R42) : valable 10 minutes, une seule fois, cinq essais au plus.
 * Renvoie le compte à connecter ; l'échec est signalé à l'écouteur du journal (événement Failed).
 */
class VerifierCodeConnexion
{
    /**
     * @throws ValidationException
     */
    public function handle(string $login, string $code): User
    {
        $utilisateur = User::where('login', trim($login))->where('actif', true)->first();

        $valide = $utilisateur !== null && DB::transaction(function () use ($utilisateur, $code): bool {
            $enCours = CodeConnexion::where('user_id', $utilisateur->id)->whereNull('utilise_le')->latest('id')->lockForUpdate()->first();

            if ($enCours === null || ! $enCours->estValide()) {
                return false;
            }

            if (! hash_equals($enCours->empreinte, DemanderCodeConnexion::empreinte(preg_replace('/\D/', '', $code)))) {
                $enCours->increment('tentatives');

                return false;
            }

            $enCours->update(['utilise_le' => now()]);

            return true;
        });

        if (! $valide) {
            event(new Failed('web', $utilisateur, ['login' => $login]));

            throw ValidationException::withMessages(['code' => 'Code incorrect ou expiré. Demandez un nouveau code si besoin.']);
        }

        return $utilisateur;
    }
}
