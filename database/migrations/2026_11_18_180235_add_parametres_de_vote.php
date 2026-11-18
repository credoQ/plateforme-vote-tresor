<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Paramètre du vote pour soi-même (R45, à confirmer par le métier) et numéro de l'accusé de vote.
     */
    public function up(): void
    {
        Schema::table('editions', function (Blueprint $table) {
            $table->boolean('vote_pour_soi_autorise')->default(true)->after('vote_blanc_autorise');
        });

        Schema::table('inscriptions', function (Blueprint $table) {
            $table->string('numero_accuse', 20)->nullable()->unique()->after('canal');
        });
    }

    public function down(): void
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->dropColumn('numero_accuse');
        });

        Schema::table('editions', function (Blueprint $table) {
            $table->dropColumn('vote_pour_soi_autorise');
        });
    }
};
