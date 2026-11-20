<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Types de structure (R7), chacun classé dans un seul pôle électoral.
     */
    public function up(): void
    {
        Schema::create('types_structure', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('libelle', 120);
            $table->foreignId('pole_electoral_id')->constrained('poles_electoraux')->restrictOnDelete();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('types_structure');
    }
};
