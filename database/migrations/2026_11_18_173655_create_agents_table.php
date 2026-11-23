<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agents de la DGTCP (R11). Les colonnes informatives ne portent aucune règle.
     */
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('matricule', 30)->unique();
            $table->string('npi', 20)->nullable()->unique();
            $table->string('nom', 100);
            $table->string('prenoms', 150);
            $table->char('sexe', 1);
            $table->date('date_naissance')->nullable();
            $table->date('date_prise_service')->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('fonction', 150)->nullable();
            $table->string('statut', 60)->nullable();
            $table->timestampsTz();

            $table->index(['nom', 'prenoms']);
            $table->index('sexe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
