<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Procès-verbaux des scrutins (R47, R51, R55). Un procès-verbal publié ne peut plus être modifié ni supprimé.
     */
    public function up(): void
    {
        Schema::create('proces_verbaux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scrutin_id')->unique()->constrained('scrutins')->restrictOnDelete();
            $table->string('numero', 30)->nullable()->unique();
            $table->timestampTz('date_etablissement');
            $table->unsignedInteger('nb_inscrits');
            $table->unsignedInteger('nb_votants');
            $table->unsignedInteger('nb_blancs');
            $table->unsignedInteger('nb_exprimes');
            $table->decimal('taux_participation', 5, 2);
            $table->boolean('quorum_atteint')->default(true);
            $table->boolean('egalite_a_departager')->default(false);
            $table->string('motif_departage', 1000)->nullable();
            $table->char('empreinte_chaine', 64)->nullable();
            $table->string('fichier')->nullable();
            $table->foreignId('valide_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('date_validation')->nullable();
            $table->timestampTz('date_publication')->nullable();
            $table->timestampsTz();
        });

        // R55 : un procès-verbal publié est archivé et ne peut plus être modifié.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION proces_verbaux_proteger_publication() RETURNS trigger AS $$
            BEGIN
                IF OLD.date_publication IS NOT NULL THEN
                    RAISE EXCEPTION 'Un procès-verbal publié ne peut être ni modifié ni supprimé (R55).';
                END IF;
                IF TG_OP = 'DELETE' THEN
                    RETURN OLD;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER proces_verbaux_archives
                BEFORE UPDATE OR DELETE ON proces_verbaux
                FOR EACH ROW EXECUTE FUNCTION proces_verbaux_proteger_publication();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('proces_verbaux');
        DB::unprepared('DROP FUNCTION IF EXISTS proces_verbaux_proteger_publication()');
    }
};
