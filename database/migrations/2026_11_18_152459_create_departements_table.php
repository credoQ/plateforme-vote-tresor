<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Départements géographiques (R9) : les 12 départements du Bénin.
     */
    public function up(): void
    {
        Schema::create('departements', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('libelle', 60)->unique();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departements');
    }
};
