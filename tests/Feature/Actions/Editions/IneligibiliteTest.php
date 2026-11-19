<?php

namespace Tests\Feature\Actions\Editions;

use App\Actions\Editions\CloturerPhase;
use App\Actions\Editions\EnregistrerEdition;
use App\Actions\Editions\ImporterFemmesModeles;
use App\Actions\Editions\SupprimerFemmeModele;
use App\Actions\Scrutins\GenererScrutins;
use App\Actions\Scrutins\NominerCandidate;
use App\Enums\Audit\ActionAuditee;
use App\Enums\Editions\SourceFemmeModele;
use App\Enums\Editions\StatutEdition;
use App\Enums\Editions\StatutPhase;
use App\Enums\Scrutins\OrigineCandidate;
use App\Enums\Scrutins\StatutScrutin;
use App\Models\Agent;
use App\Models\Candidate;
use App\Models\Edition;
use App\Models\FemmeModele;
use App\Models\JournalAudit;
use App\Models\Phase;
use App\Models\Scrutin;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * R59 : une femme modèle n'est de nouveau candidate que lorsque année de l'édition − année d'élection dépasse le délai.
 */
class IneligibiliteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * R59 : avec un délai de 3 ans, une élue de 2024 est inéligible jusqu'en 2027 et de nouveau candidate en 2028.
     */
    public function test_r59_applique_le_delai_de_reeligibilite_de_l_edition(): void
    {
        FemmeModele::factory()->create(['matricule' => '104876', 'annee' => 2024]);
        FemmeModele::factory()->create(['matricule' => '104877', 'annee' => 2023]);

        $edition2027 = Edition::factory()->make(['annee' => 2027, 'delai_reeligibilite' => 3]);
        $this->assertSame(['104876' => 2024], $edition2027->ineligibles()->all());
        $this->assertStringContainsString('qu’à partir de 2028', $edition2027->motifIneligibilite('104876'));
        $this->assertNull($edition2027->motifIneligibilite('104877'));

        $this->assertSame([], Edition::factory()->make(['annee' => 2028, 'delai_reeligibilite' => 3])->ineligibles()->all());
        $this->assertSame(['104877' => 2023, '104876' => 2024], Edition::factory()->make(['annee' => 2027, 'delai_reeligibilite' => 4])->ineligibles()->all());
    }

    /**
     * R28, R59 : une femme modèle inéligible n'est pas candidate d'office, mais reste électrice ; sa nomination est refusée.
     */
    public function test_r59_ecarte_une_femme_modele_ineligible_des_candidates(): void
    {
        $edition = Edition::factory()->avecPhases()->enCours()->create(['annee' => 2026, 'date_debut' => '2026-11-01', 'date_fin' => '2026-12-31', 'date_gel' => '2026-10-01']);
        $dia = Structure::where('code', 'DIA')->firstOrFail();
        $ancienne = Agent::factory()->femme()->affecteA($dia, Carbon::parse('2020-01-01'))->create(['matricule' => '104876']);
        $autre = Agent::factory()->femme()->affecteA($dia, Carbon::parse('2020-01-01'))->create();
        FemmeModele::factory()->create(['matricule' => '104876', 'annee' => 2024]);

        app(GenererScrutins::class)->handle($edition->phase(Phase::PRIMAIRES));
        $scrutin = Scrutin::where('code', 'STR-DIA')->firstOrFail();

        $this->assertSame([$autre->id], $scrutin->candidates()->pluck('agent_id')->all());
        $this->assertTrue($scrutin->inscriptions()->where('agent_id', $ancienne->id)->exists(), 'Elle reste électrice.');

        $scrutin->college->update(['origine_candidates' => OrigineCandidate::Nomination]);
        $scrutin->candidates()->delete();
        try {
            app(NominerCandidate::class)->handle($scrutin->fresh(), '104876', 'PV 1', User::factory()->create());
            $this->fail('La nomination d’une femme modèle inéligible devait être refusée.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('inéligible', $exception->errors()['matricule'][0]);
            $this->assertStringContainsString('2028', $exception->errors()['matricule'][0]);
        }
    }

    /**
     * R53, R59 : à la clôture de la dernière phase, les femmes modèles de l'édition entrent dans l'historique.
     */
    public function test_r59_inscrit_les_femmes_modeles_dans_l_historique_a_la_cloture(): void
    {
        $edition = Edition::factory()->avecPhases([1 => ['libelle' => 'Phase unique', 'colleges' => [
            'DGTCP' => ['decoupage' => 'ENSEMBLE', 'origine' => 'AUTOMATIQUE', 'nb_elues' => 2],
        ]]])->enCours()->create(['annee' => 2026]);
        $phase = $edition->phase(1);
        $phase->update(['statut' => StatutPhase::Ouverte]);
        $scrutin = Scrutin::factory()->for($phase)->statut(StatutScrutin::ResultatsPublies)->create(['libelle' => 'Scrutin de l’ensemble de la DGTCP']);
        $elues = Agent::factory()->femme()->count(2)->create();
        foreach ($elues as $rang => $agent) {
            Candidate::factory()->for($scrutin)->for($agent)->laureate()->create(['rang' => $rang + 1]);
        }

        app(CloturerPhase::class)->handle($phase);

        $this->assertSame(StatutEdition::Cloturee, $edition->fresh()->statut);
        $historique = FemmeModele::orderBy('matricule')->get();
        $this->assertEqualsCanonicalizing($elues->pluck('matricule')->all(), $historique->pluck('matricule')->all());
        $this->assertTrue($historique->every(fn (FemmeModele $f): bool => $f->annee === 2026 && $f->source === SourceFemmeModele::Plateforme && $f->scrutin === 'Scrutin de l’ensemble de la DGTCP'));
    }

    /**
     * R59 : import des éditions antérieures ; les lignes incomplètes ou déjà proclamées par la plateforme sont rejetées.
     */
    public function test_r59_importe_l_historique_des_editions_anterieures(): void
    {
        Agent::factory()->femme()->create(['matricule' => '104876', 'nom' => 'AHOUANSOU', 'prenoms' => 'Clarisse']);
        FemmeModele::factory()->create(['matricule' => '300000', 'annee' => 2025, 'source' => SourceFemmeModele::Plateforme]);
        $chemin = $this->fichier("matricule;annee;nom;scrutin\n104876;2024;;Finale\n200001;2023;DOSSOU Prisca;\n200002;2023;;\n200003;23;KPADE Inès;\n300000;2025;HOUNSOU Afiavi;\n");

        $rapport = app(ImporterFemmesModeles::class)->handle($chemin);

        $this->assertSame(2, $rapport->enregistrees);
        $this->assertSame([4, 5, 6], array_keys($rapport->rejets));
        $clarisse = FemmeModele::where('matricule', '104876')->sole();
        $this->assertSame(['AHOUANSOU Clarisse', 2024, 'Finale', SourceFemmeModele::Import], [$clarisse->nom_complet, $clarisse->annee, $clarisse->scrutin, $clarisse->source]);
        $this->assertNotNull($clarisse->agent_id);
        $this->assertSame(1, JournalAudit::where('action', ActionAuditee::ImportFemmesModeles->value)->count());
    }

    public function test_r59_retire_une_ligne_importee_mais_pas_une_femme_modele_proclamee(): void
    {
        $importee = FemmeModele::factory()->create();
        $proclamee = FemmeModele::factory()->create(['source' => SourceFemmeModele::Plateforme]);

        app(SupprimerFemmeModele::class)->handle($importee);
        $this->assertModelMissing($importee);

        $this->expectException(ValidationException::class);
        app(SupprimerFemmeModele::class)->handle($proclamee);
    }

    public function test_r59_le_delai_de_reeligibilite_est_un_parametre_de_l_edition(): void
    {
        $edition = app(EnregistrerEdition::class)->handle([
            'annee' => 2027, 'libelle' => 'Édition 2027', 'date_debut' => '2027-03-01', 'date_fin' => '2027-04-30', 'date_gel' => '2027-02-15', 'delai_reeligibilite' => 5,
        ]);
        $this->assertSame(5, $edition->fresh()->delai_reeligibilite);

        $this->expectException(ValidationException::class);
        app(EnregistrerEdition::class)->handle(['annee' => 2028, 'libelle' => 'Édition 2028', 'date_debut' => '2028-03-01', 'date_fin' => '2028-04-30', 'date_gel' => '2028-02-15', 'delai_reeligibilite' => 21]);
    }

    private function fichier(string $contenu): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'fm').'.csv';
        file_put_contents($chemin, $contenu);

        return $chemin;
    }
}
