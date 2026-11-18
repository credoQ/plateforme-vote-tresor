<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Structures de la DGTCP (R5, R8, R9, R10).
     *
     * Le niveau 1 désigne une structure électorale autonome (direction, cellule, TMIE, ACD, TD, TC),
     * le niveau 2 un service rattaché à une structure de niveau 1.
     */
    public function up(): void
    {
        Schema::create('structures', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('sigle', 20)->nullable();
            $table->string('libelle', 160);
            $table->unsignedTinyInteger('niveau')->default(1);
            $table->boolean('actif')->default(true);
            $table->foreignId('type_structure_id')->constrained('types_structure')->restrictOnDelete();
            $table->foreignId('departement_id')->nullable()->constrained('departements')->restrictOnDelete();
            $table->foreignId('structure_parente_id')->nullable()->constrained('structures')->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['type_structure_id', 'actif']);
            $table->index('departement_id');
            $table->index('structure_parente_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('structures');
    }
};
