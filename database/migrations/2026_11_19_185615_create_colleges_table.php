<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Collèges des phases (R17, R20 à R23) : chaque phase déclare ses collèges (pôle électoral, ou ensemble de la DGTCP),
 * leur découpage en scrutins, l'origine des candidates et le nombre d'élues par scrutin.
 *
 * Les éditions existantes reçoivent l'organisation en trois phases qui était codée en dur, avec le nombre de qualifiées
 * de chaque phase ; ce nombre passe ensuite des phases aux collèges.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('colleges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phase_id')->constrained('phases')->cascadeOnDelete();
            $table->foreignId('pole_electoral_id')->nullable()->constrained('poles_electoraux')->restrictOnDelete();
            $table->string('decoupage', 30);
            $table->string('origine_candidates', 20);
            $table->unsignedSmallInteger('nb_elues')->default(1);
            $table->timestampsTz();

            $table->unique(['phase_id', 'pole_electoral_id']);
        });

        DB::statement('CREATE UNIQUE INDEX colleges_dgtcp_unique ON colleges (phase_id) WHERE pole_electoral_id IS NULL');
        DB::statement('ALTER TABLE colleges ADD CONSTRAINT colleges_nb_elues_positif CHECK (nb_elues >= 1)');

        Schema::table('scrutins', function (Blueprint $table) {
            $table->foreignId('college_id')->nullable()->after('phase_id')->constrained('colleges')->restrictOnDelete();
        });

        $this->reprendreLesEditionsExistantes();

        DB::statement('ALTER TABLE scrutins ALTER COLUMN college_id SET NOT NULL');

        Schema::table('phases', function (Blueprint $table) {
            $table->dropColumn('nb_qualifiees');
        });
    }

    public function down(): void
    {
        Schema::table('phases', function (Blueprint $table) {
            $table->unsignedSmallInteger('nb_qualifiees')->default(1);
        });

        DB::statement('UPDATE phases SET nb_qualifiees = COALESCE((SELECT MAX(nb_elues) FROM colleges WHERE colleges.phase_id = phases.id), 1)');

        Schema::table('scrutins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('college_id');
        });

        Schema::dropIfExists('colleges');
    }

    /**
     * Organisation en trois phases d'avant le paramétrage, et rattachement des scrutins déjà générés à leur collège.
     */
    private function reprendreLesEditionsExistantes(): void
    {
        $poles = DB::table('poles_electoraux')->pluck('id', 'code');
        $organisation = [
            1 => [['DEPARTEMENTAL', 'PAR_CIRCONSCRIPTION', 'AUTOMATIQUE'], ['STRUCTURES', 'PAR_CIRCONSCRIPTION', 'AUTOMATIQUE']],
            2 => [['DEPARTEMENTAL', 'ENSEMBLE', 'QUALIFICATION'], ['STRUCTURES', 'ENSEMBLE', 'QUALIFICATION']],
            3 => [[null, 'ENSEMBLE', 'QUALIFICATION']],
        ];

        foreach (DB::table('phases')->get() as $phase) {
            foreach ($organisation[$phase->numero] ?? [] as [$pole, $decoupage, $origine]) {
                $poleId = $pole === null ? null : $poles[$pole] ?? null;
                if ($pole !== null && $poleId === null) {
                    continue;
                }

                $collegeId = DB::table('colleges')->insertGetId([
                    'phase_id' => $phase->id,
                    'pole_electoral_id' => $poleId,
                    'decoupage' => $decoupage,
                    'origine_candidates' => $origine,
                    'nb_elues' => $phase->nb_qualifiees ?? 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('scrutins')
                    ->where('phase_id', $phase->id)
                    ->where(fn ($query) => $poleId === null ? $query->whereNull('pole_electoral_id') : $query->where('pole_electoral_id', $poleId))
                    ->update(['college_id' => $collegeId]);
            }
        }

        // Filet de sécurité : un scrutin dont le pôle ne correspond à aucun collège rejoint le premier collège de sa phase.
        DB::statement('UPDATE scrutins SET college_id = (SELECT MIN(colleges.id) FROM colleges WHERE colleges.phase_id = scrutins.phase_id) WHERE college_id IS NULL');
    }
};
