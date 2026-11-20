<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scrutins d'une phase (R19, R22). Leur génération et leur circonscription relèvent du module 05.
     */
    public function up(): void
    {
        Schema::create('scrutins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phase_id')->constrained('phases')->cascadeOnDelete();
            $table->foreignId('pole_electoral_id')->nullable()->constrained('poles_electoraux')->restrictOnDelete();
            $table->string('code', 40);
            $table->string('libelle', 200);
            $table->timestampTz('date_heure_ouverture')->nullable();
            $table->timestampTz('date_heure_fermeture')->nullable();
            $table->decimal('quorum', 5, 2)->nullable();
            $table->string('statut', 20)->default('EN_PREPARATION');
            $table->timestampsTz();

            $table->unique(['phase_id', 'code']);
            $table->index(['phase_id', 'statut']);
        });

        DB::statement('ALTER TABLE scrutins ADD CONSTRAINT scrutins_periode_valide CHECK (date_heure_ouverture IS NULL OR date_heure_fermeture IS NULL OR date_heure_ouverture < date_heure_fermeture)');
        DB::statement('ALTER TABLE scrutins ADD CONSTRAINT scrutins_quorum_valide CHECK (quorum IS NULL OR (quorum > 0 AND quorum <= 100))');
    }

    public function down(): void
    {
        Schema::dropIfExists('scrutins');
    }
};
