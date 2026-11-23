<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables dont les fiches s'ouvrent par une adresse : l'adresse porte un uuid, jamais le numéro de la ligne.
     */
    private const TABLES = ['editions', 'phases', 'scrutins', 'agents', 'structures', 'candidates', 'users', 'femmes_modeles'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $colonnes) {
                $colonnes->uuid('uuid')->nullable();
            });

            DB::statement("UPDATE {$table} SET uuid = gen_random_uuid() WHERE uuid IS NULL");

            Schema::table($table, function (Blueprint $colonnes) {
                $colonnes->uuid('uuid')->nullable(false)->change();
                $colonnes->unique('uuid');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $colonnes) {
                $colonnes->dropUnique(['uuid']);
                $colonnes->dropColumn('uuid');
            });
        }
    }
};
