<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photo facultative d'une candidate, affichée sur le bulletin : chemin du fichier sur le disque privé, ou null
 * (la candidate apparaît alors avec ses initiales).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->string('photo')->nullable()->after('motif_desistement');
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn('photo');
        });
    }
};
