<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Journal d'audit des opérations sensibles (R56), en ajout seul : aucune mise à jour, aucune suppression.
     * L'utilisateur est recopié (identifiant et login) sans clé étrangère, pour que l'entrée survive au compte.
     */
    public function up(): void
    {
        Schema::create('journal_audit', function (Blueprint $table) {
            $table->id();
            $table->timestampTz('date_heure')->useCurrent();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('login', 60)->nullable();
            $table->string('action', 40);
            $table->string('entite_type', 40)->nullable();
            $table->unsignedBigInteger('entite_id')->nullable();
            $table->jsonb('details')->nullable();
            $table->string('adresse_ip', 45)->nullable();

            $table->index('date_heure');
            $table->index(['action', 'date_heure']);
            $table->index(['entite_type', 'entite_id']);
            $table->index('user_id');
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION journal_audit_ajout_seul() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Le journal d''audit est en ajout seul : aucune modification ni suppression (R56).';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER journal_audit_inalterable
                BEFORE UPDATE OR DELETE ON journal_audit
                FOR EACH ROW EXECUTE FUNCTION journal_audit_ajout_seul();

            CREATE TRIGGER journal_audit_sans_vidage
                BEFORE TRUNCATE ON journal_audit
                FOR EACH STATEMENT EXECUTE FUNCTION journal_audit_ajout_seul();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_audit');
        DB::unprepared('DROP FUNCTION IF EXISTS journal_audit_ajout_seul()');
    }
};
