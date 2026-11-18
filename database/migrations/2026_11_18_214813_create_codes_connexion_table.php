<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Codes à usage unique envoyés par e-mail pour se connecter (R42). Le code n'est stocké que sous forme d'empreinte.
     */
    public function up(): void
    {
        Schema::create('codes_connexion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->char('empreinte', 64);
            $table->timestampTz('expire_le');
            $table->unsignedTinyInteger('tentatives')->default(0);
            $table->timestampTz('utilise_le')->nullable();
            $table->string('adresse_ip', 45)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['user_id', 'utilise_le']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codes_connexion');
    }
};
