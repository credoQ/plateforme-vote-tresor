<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pôles électoraux (R6) : pôle départemental et pôle structures.
     */
    public function up(): void
    {
        Schema::create('poles_electoraux', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('libelle', 120);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poles_electoraux');
    }
};
