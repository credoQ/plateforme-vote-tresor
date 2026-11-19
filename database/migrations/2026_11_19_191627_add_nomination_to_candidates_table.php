<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomination d'une candidate par le comité (R28, R58) : référence de la décision, auteur et date de la saisie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->string('reference_nomination', 200)->nullable()->after('origine');
            $table->foreignId('nommee_par_id')->nullable()->after('reference_nomination')->constrained('users')->nullOnDelete();
            $table->timestampTz('date_nomination')->nullable()->after('nommee_par_id');
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('nommee_par_id');
            $table->dropColumn(['reference_nomination', 'date_nomination']);
        });
    }
};
