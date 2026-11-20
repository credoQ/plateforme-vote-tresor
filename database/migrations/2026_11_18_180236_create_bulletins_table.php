<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bulletins (R41, R43, R44). Aucune colonne ne relie un bulletin à un agent ou à un utilisateur :
     * identifiant aléatoire, pas de created_at, horodatage tronqué à l'heure.
     */
    public function up(): void
    {
        Schema::create('bulletins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('scrutin_id')->constrained('scrutins')->restrictOnDelete();
            $table->foreignId('candidate_id')->nullable()->constrained('candidates')->restrictOnDelete();
            $table->boolean('est_blanc')->default(false);
            $table->timestampTz('date_heure');
            $table->char('empreinte', 64)->unique();
            $table->char('empreinte_precedente', 64)->nullable();

            $table->index(['scrutin_id', 'candidate_id']);
        });

        DB::statement('ALTER TABLE bulletins ADD CONSTRAINT bulletins_choix_valide CHECK ((est_blanc AND candidate_id IS NULL) OR (NOT est_blanc AND candidate_id IS NOT NULL))');
        DB::statement('CREATE UNIQUE INDEX bulletins_un_seul_debut_de_chaine ON bulletins (scrutin_id) WHERE empreinte_precedente IS NULL');
        DB::statement('CREATE UNIQUE INDEX bulletins_chaine_sans_embranchement ON bulletins (empreinte_precedente) WHERE empreinte_precedente IS NOT NULL');

        // R44, R55 : un bulletin n'est jamais modifié ni supprimé.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION bulletins_interdire_modification() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Un bulletin ne peut être ni modifié ni supprimé (R44).';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER bulletins_immuables
                BEFORE UPDATE OR DELETE ON bulletins
                FOR EACH ROW EXECUTE FUNCTION bulletins_interdire_modification();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('bulletins');
        DB::unprepared('DROP FUNCTION IF EXISTS bulletins_interdire_modification()');
    }
};
