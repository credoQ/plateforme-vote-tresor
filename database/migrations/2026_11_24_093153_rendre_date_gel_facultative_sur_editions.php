<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La date de gel n'est plus saisie à la création de l'édition : elle est fixée le jour du premier arrêt
     * des listes (R14, R16). Elle reste vide jusque-là ; le contrôle des dates ne porte plus que sur l'édition.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE editions DROP CONSTRAINT IF EXISTS editions_dates_valides');

        Schema::table('editions', function (Blueprint $table) {
            $table->date('date_gel')->nullable()->change();
        });

        DB::statement('ALTER TABLE editions ADD CONSTRAINT editions_dates_valides CHECK (date_debut <= date_fin)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE editions DROP CONSTRAINT IF EXISTS editions_dates_valides');
        DB::statement('UPDATE editions SET date_gel = date_debut WHERE date_gel IS NULL');

        Schema::table('editions', function (Blueprint $table) {
            $table->date('date_gel')->nullable(false)->change();
        });

        DB::statement('ALTER TABLE editions ADD CONSTRAINT editions_dates_valides CHECK (date_debut <= date_fin AND date_gel <= date_fin)');
    }
};
