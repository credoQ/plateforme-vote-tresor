<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rôles (R2) et leur attribution aux utilisateurs : un utilisateur possède un ou plusieurs rôles.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('libelle', 80);
            $table->timestampsTz();
        });

        Schema::create('role_utilisateur', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();

            $table->primary(['user_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_utilisateur');
        Schema::dropIfExists('roles');
    }
};
