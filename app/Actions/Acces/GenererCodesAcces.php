<?php

namespace App\Actions\Acces;

use App\Actions\Audit\Journaliser;
use App\Enums\Audit\ActionAuditee;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Codes personnels du mode « code imprimé » (ModeConnexion::CodeImprime) : un code par compte actif, valable pour
 * toute l'édition, remis sur un coupon par le comité. Génère les codes manquants sans toucher aux codes existants ;
 * le journal (R56) trace le nombre de codes créés, jamais les codes.
 */
class GenererCodesAcces
{
    /**
     * Caractères faciles à lire et à saisir : sans 0/O, 1/I/L.
     */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const LONGUEUR = 8;

    public function __construct(private Journaliser $journal) {}

    public static function nouveauCode(): string
    {
        $code = '';
        for ($i = 0; $i < self::LONGUEUR; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }

    /**
     * Code saisi ramené à sa forme enregistrée : majuscules, sans espace ni tiret.
     */
    public static function normaliser(string $code): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    /**
     * Code affiché sur le coupon, en deux blocs : K7RM-4XQP.
     */
    public static function formater(string $code): string
    {
        return implode('-', str_split($code, 4));
    }

    /**
     * @return int Nombre de codes créés.
     */
    public function handle(?User $auteur = null): int
    {
        return DB::transaction(function () use ($auteur): int {
            $comptes = User::where('actif', true)->whereNull('code_acces')->lockForUpdate()->get();

            foreach ($comptes as $compte) {
                $compte->forceFill(['code_acces' => self::nouveauCode(), 'code_acces_genere_le' => now()])->save();
            }

            if ($comptes->isNotEmpty()) {
                $this->journal->handle(ActionAuditee::CodesAccesGeneres, null, ['nombre' => $comptes->count()], $auteur);
            }

            return $comptes->count();
        });
    }

    /**
     * Nouveau code pour un compte (code perdu) : l'ancien cesse aussitôt de fonctionner.
     */
    public function regenerer(User $compte, ?User $auteur = null): User
    {
        return DB::transaction(function () use ($compte, $auteur): User {
            $compte = User::lockForUpdate()->findOrFail($compte->id);
            $compte->forceFill(['code_acces' => self::nouveauCode(), 'code_acces_genere_le' => now()])->save();
            $this->journal->handle(ActionAuditee::CodeAccesRegenere, $compte, ['login' => $compte->login], $auteur);

            return $compte;
        });
    }
}
