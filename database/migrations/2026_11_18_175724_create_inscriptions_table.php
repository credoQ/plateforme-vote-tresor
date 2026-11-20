<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Listes électorales (association INSCRIRE, R24 à R27) et émargement (R40, R43).
     * La colonne phase_id garantit qu'un agent n'est inscrit qu'une fois par phase (R26).
     */
    public function up(): void
    {
        Schema::create('inscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scrutin_id')->constrained('scrutins')->cascadeOnDelete();
            $table->foreignId('phase_id')->constrained('phases')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('agents')->restrictOnDelete();
            $table->foreignId('structure_id')->constrained('structures')->restrictOnDelete();
            $table->boolean('a_vote')->default(false);
            $table->timestampTz('date_heure_vote')->nullable();
            $table->string('canal', 20)->nullable();
            $table->timestampsTz();

            $table->unique(['scrutin_id', 'agent_id']);
            $table->unique(['phase_id', 'agent_id']);
            $table->index(['scrutin_id', 'a_vote']);
            $table->index('structure_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscriptions');
    }
};
