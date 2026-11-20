<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * R44 : le scrutin garde l'empreinte du dernier bulletin de sa chaîne. Le vote la lit sur la ligne du scrutin,
     * déjà verrouillée, au lieu de chercher parmi tous les bulletins le seul qu'aucun autre ne suit.
     */
    public function up(): void
    {
        Schema::table('scrutins', function (Blueprint $table) {
            $table->char('derniere_empreinte', 64)->nullable();
        });

        DB::statement(<<<'SQL'
            UPDATE scrutins SET derniere_empreinte = (
                SELECT bulletins.empreinte FROM bulletins
                WHERE bulletins.scrutin_id = scrutins.id
                  AND NOT EXISTS (SELECT 1 FROM bulletins AS suivants WHERE suivants.empreinte_precedente = bulletins.empreinte)
                LIMIT 1
            )
            SQL);
    }

    public function down(): void
    {
        Schema::table('scrutins', function (Blueprint $table) {
            $table->dropColumn('derniere_empreinte');
        });
    }
};
