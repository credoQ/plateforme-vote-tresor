<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comptes utilisateurs (R1, R3, R4) : login (matricule pour un agent), statut, dernière connexion,
     * lien facultatif et unique vers un agent, structure de rattachement du point focal.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('login', 60)->unique()->after('name');
            $table->string('email')->nullable()->change();
            $table->boolean('actif')->default(true)->after('password');
            $table->boolean('mot_de_passe_a_changer')->default(false)->after('actif');
            $table->timestampTz('derniere_connexion')->nullable()->after('mot_de_passe_a_changer');
            $table->foreignId('agent_id')->nullable()->unique()->after('derniere_connexion')->constrained('agents')->nullOnDelete();
            $table->foreignId('structure_id')->nullable()->after('agent_id')->constrained('structures')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('structure_id');
            $table->dropConstrainedForeignId('agent_id');
            $table->dropColumn(['login', 'actif', 'mot_de_passe_a_changer', 'derniere_connexion']);
        });
    }
};
