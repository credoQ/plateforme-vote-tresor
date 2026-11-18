<?php

namespace App\Console\Commands;

use App\Actions\Acces\EnregistrerUtilisateur;
use App\Enums\Acces\CodeRole;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

#[Signature('acces:creer-administrateur {login : Identifiant du compte} {nom : Nom affiché}')]
#[Description('Crée un compte administrateur avec un mot de passe provisoire à changer à la première connexion')]
class CreerAdministrateurCommand extends Command
{
    public function handle(EnregistrerUtilisateur $enregistrer): int
    {
        try {
            ['mot_de_passe' => $motDePasse] = $enregistrer->handle([
                'name' => $this->argument('nom'),
                'login' => $this->argument('login'),
                'roles' => [CodeRole::Administrateur->value],
            ]);
        } catch (ValidationException $exception) {
            $this->components->error(implode(' ', $exception->validator->errors()->all()));

            return self::FAILURE;
        }

        $this->components->info("Administrateur {$this->argument('login')} créé.");
        $this->components->twoColumnDetail('Mot de passe provisoire', $motDePasse);

        return self::SUCCESS;
    }
}
