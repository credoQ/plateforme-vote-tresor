<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Circonscription d'un scrutin (association COUVRIR, R24) : les structures qu'il couvre.
     */
    public function up(): void
    {
        Schema::create('couvertures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scrutin_id')->constrained('scrutins')->cascadeOnDelete();
            $table->foreignId('structure_id')->constrained('structures')->restrictOnDelete();
            $table->timestampsTz();

            $table->unique(['scrutin_id', 'structure_id']);
            $table->index('structure_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('couvertures');
    }
};
