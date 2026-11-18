<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Éditions annuelles du concours (R16) et leurs paramètres encore à valider par le métier.
     */
    public function up(): void
    {
        Schema::create('editions', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('annee')->unique();
            $table->string('libelle', 160);
            $table->date('date_debut');
            $table->date('date_fin');
            $table->date('date_gel');
            $table->string('statut', 20)->default('EN_PREPARATION');
            $table->boolean('vote_blanc_autorise')->default(false);
            $table->decimal('quorum', 5, 2)->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE editions ADD CONSTRAINT editions_dates_valides CHECK (date_debut <= date_fin AND date_gel <= date_fin)');
        DB::statement('ALTER TABLE editions ADD CONSTRAINT editions_quorum_valide CHECK (quorum IS NULL OR (quorum > 0 AND quorum <= 100))');
        DB::statement("CREATE UNIQUE INDEX editions_une_seule_en_cours ON editions ((true)) WHERE statut = 'EN_COURS'");
    }

    public function down(): void
    {
        Schema::dropIfExists('editions');
    }
};
