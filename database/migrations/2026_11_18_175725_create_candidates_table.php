<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Candidates d'un scrutin (R28 à R36). Une candidate qualifiée garde le lien avec sa candidature d'origine (QUALIFIER).
     */
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scrutin_id')->constrained('scrutins')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('agents')->restrictOnDelete();
            $table->string('origine', 20);
            $table->string('statut', 20)->default('ACTIVE');
            $table->timestampTz('date_desistement')->nullable();
            $table->string('motif_desistement', 500)->nullable();
            $table->foreignId('desactivee_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('candidate_source_id')->nullable()->constrained('candidates')->restrictOnDelete();
            $table->unsignedInteger('nb_voix')->nullable();
            $table->unsignedSmallInteger('rang')->nullable();
            $table->boolean('est_laureate')->default(false);
            $table->timestampsTz();

            $table->unique(['scrutin_id', 'agent_id']);
            $table->index(['scrutin_id', 'statut']);
            $table->index('candidate_source_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
