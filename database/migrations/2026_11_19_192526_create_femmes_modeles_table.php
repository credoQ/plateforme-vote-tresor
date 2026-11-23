<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inéligibilité (R59) : historique des femmes modèles du Trésor, alimenté à la clôture de chaque édition et complété
 * par import pour les éditions antérieures à la plateforme, et délai de rééligibilité paramétré sur l'édition.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('femmes_modeles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('matricule', 30);
            $table->foreignId('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->string('nom_complet', 200);
            $table->unsignedSmallInteger('annee');
            $table->foreignId('edition_id')->nullable()->constrained('editions')->nullOnDelete();
            $table->string('scrutin', 200)->nullable();
            $table->string('source', 20);
            $table->timestampsTz();

            $table->unique(['matricule', 'annee']);
            $table->index('annee');
        });

        Schema::table('editions', function (Blueprint $table) {
            $table->unsignedSmallInteger('delai_reeligibilite')->default(3)->after('quorum');
        });

        DB::statement('ALTER TABLE editions ADD CONSTRAINT editions_delai_reeligibilite_valide CHECK (delai_reeligibilite <= 20)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE editions DROP CONSTRAINT IF EXISTS editions_delai_reeligibilite_valide');

        Schema::table('editions', function (Blueprint $table) {
            $table->dropColumn('delai_reeligibilite');
        });

        Schema::dropIfExists('femmes_modeles');
    }
};
