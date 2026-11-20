<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Affectations des agents (association AFFECTER, R12) : l'historique est conservé.
     */
    public function up(): void
    {
        Schema::create('affectations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignId('structure_id')->constrained('structures')->restrictOnDelete();
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->timestampsTz();

            $table->index(['agent_id', 'date_debut']);
            $table->index(['structure_id', 'date_fin']);
        });

        DB::statement('ALTER TABLE affectations ADD CONSTRAINT affectations_periode_valide CHECK (date_fin IS NULL OR date_fin >= date_debut)');
        DB::statement('CREATE UNIQUE INDEX affectations_une_en_cours_par_agent ON affectations (agent_id) WHERE date_fin IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('affectations');
    }
};
