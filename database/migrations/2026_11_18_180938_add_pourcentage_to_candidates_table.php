<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pourcentage des suffrages exprimés obtenu par la candidate (R47).
     */
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->decimal('pourcentage', 5, 2)->nullable()->after('nb_voix');
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn('pourcentage');
        });
    }
};
