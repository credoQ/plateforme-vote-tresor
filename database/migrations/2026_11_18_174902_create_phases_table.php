<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Les trois phases ordonnées d'une édition (R17).
     */
    public function up(): void
    {
        Schema::create('phases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->unsignedTinyInteger('numero');
            $table->string('libelle', 80);
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->unsignedTinyInteger('nb_qualifiees')->default(1);
            $table->string('statut', 20)->default('PLANIFIEE');
            $table->timestampTz('date_ouverture')->nullable();
            $table->timestampTz('date_cloture')->nullable();
            $table->timestampsTz();

            $table->unique(['edition_id', 'numero']);
        });

        DB::statement('ALTER TABLE phases ADD CONSTRAINT phases_numero_valide CHECK (numero BETWEEN 1 AND 3)');
        DB::statement('ALTER TABLE phases ADD CONSTRAINT phases_dates_valides CHECK (date_debut IS NULL OR date_fin IS NULL OR date_debut <= date_fin)');
        DB::statement('ALTER TABLE phases ADD CONSTRAINT phases_nb_qualifiees_valide CHECK (nb_qualifiees >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('phases');
    }
};
