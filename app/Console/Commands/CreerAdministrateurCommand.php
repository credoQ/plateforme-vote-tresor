<?php

namespace App\Console\Commands;

use App\Actions\Acces\EnregistrerUtilisateur;
use App\Enums\Acces\CodeRole;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

#[Signature('acces:creer-administrateur {login : Identifiant du compte} {nom : Nom affiché} {email : Adresse qui recevra les codes de connexion}')]
#[Description('Crée un compte administrateur ; il se connecte avec un code envoyé à son adresse e-mail')]
class CreerAdministrateurCommand extends Command
{
    public function handle(EnregistrerUtilisateur $enregistrer): int
    {
        try {
            $enregistrer->handle([
                'name' => $this->argument('nom'),
                'login' => $this->argument('login'),
                'email' => $this->argument('email'),
                'roles' => [CodeRole::Administrateur->value],
            ]);
        } catch (ValidationException $exception) {
            $this->components->error(implode(' ', $exception->validator->errors()->all()));

            return self::FAILURE;
        }

        $this->components->info("Administrateur {$this->argument('login')} créé : connexion par code envoyé à {$this->argument('email')}.");

        return self::SUCCESS;
    }
}
