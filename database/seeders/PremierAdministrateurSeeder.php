<?php

namespace Database\Seeders;

use App\Actions\Acces\EnregistrerUtilisateur;
use App\Enums\Acces\CodeRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Crée le premier administrateur décrit dans la configuration (PREMIER_ADMIN_* du .env), s'il n'existe pas (R2).
 * Idempotent : un compte déjà présent sous cet identifiant n'est pas modifié.
 */
class PremierAdministrateurSeeder extends Seeder
{
    public function run(EnregistrerUtilisateur $enregistrer): void
    {
        /** @var array{login: ?string, nom: ?string, email: ?string} $administrateur */
        $administrateur = config('app.premier_administrateur');

        if (blank($administrateur['login']) || User::where('login', $administrateur['login'])->exists()) {
            return;
        }

        $enregistrer->handle([
            'login' => $administrateur['login'],
            'name' => $administrateur['nom'],
            'email' => $administrateur['email'],
            'roles' => [CodeRole::Administrateur->value],
        ]);
    }
}
