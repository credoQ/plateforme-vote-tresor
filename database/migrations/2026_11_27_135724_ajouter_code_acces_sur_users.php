<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Code personnel du mode de connexion « code imprimé » (CONNEXION_MODE=code_imprime) : chiffré avec la clé de
 * l'application, pour être comparé à la saisie et réimprimé sur un coupon ; jamais affiché à l'écran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('code_acces')->nullable()->after('email');
            $table->timestamp('code_acces_genere_le')->nullable()->after('code_acces');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['code_acces', 'code_acces_genere_le']);
        });
    }
};
