<?php

namespace Tests\Feature\Actions\Editions;

use App\Actions\Editions\CloturerPhase;
use App\Actions\Editions\LancerEdition;
use App\Actions\Editions\OuvrirPhase;
use App\Enums\Editions\StatutEdition;
use App\Enums\Editions\StatutPhase;
use App\Enums\Scrutins\StatutScrutin;
use App\Models\Edition;
use App\Models\Phase;
use App\Models\Scrutin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DeroulementDesPhasesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * R16 : une seule édition peut être en cours.
     */
    public function test_r16_refuse_de_lancer_une_edition_si_une_autre_est_en_cours(): void
    {
        Edition::factory()->enCours()->create(['annee' => 2026]);
        $edition = $this->editionPlanifiee();

        $this->attendreErreur(fn () => app(LancerEdition::class)->handle($edition), 'une seule édition');
        $this->assertSame(StatutEdition::EnPreparation, $edition->fresh()->statut);
    }

    /**
     * R17 : l'édition ne se lance qu'avec ses trois phases planifiées.
     */
    public function test_r17_refuse_de_lancer_une_edition_dont_les_phases_ne_sont_pas_planifiees(): void
    {
        $edition = Edition::factory()->avecPhases()->create();

        $this->attendreErreur(fn () => app(LancerEdition::class)->handle($edition), 'planifiées');
    }

    /**
     * R18 : la phase 2 ne s'ouvre qu'une fois la phase 1 clôturée.
     */
    public function test_r18_refuse_d_ouvrir_une_phase_avant_la_cloture_de_la_precedente(): void
    {
        $edition = $this->editionLancee(fn (Edition $e) => Scrutin::factory()->for($e->phase(1))->create());
        Scrutin::factory()->for($edition->phase(2))->create();

        $this->attendreErreur(fn () => $this->ouvrir($edition->phase(2)), 'doit être clôturée');
    }

    /**
     * R18 : une phase ne se clôture que lorsque tous ses scrutins ont leurs résultats publiés ou sont annulés.
     */
    public function test_r18_refuse_de_cloturer_une_phase_dont_un_resultat_n_est_pas_publie(): void
    {
        $edition = $this->editionLancee(function (Edition $e): void {
            Scrutin::factory()->for($e->phase(1))->statut(StatutScrutin::ResultatsPublies)->create();
            Scrutin::factory()->for($e->phase(1))->statut(StatutScrutin::ResultatsValides)->create(['libelle' => 'Scrutin du Zou']);
        });
        $phase1 = $edition->phase(1);
        $this->ouvrir($phase1);

        $this->attendreErreur(fn () => app(CloturerPhase::class)->handle($phase1), 'Scrutin du Zou');
        $this->assertSame(StatutPhase::Ouverte, $phase1->fresh()->statut);
    }

    public function test_refuse_d_ouvrir_une_phase_sans_scrutin(): void
    {
        $edition = $this->editionLancee(fn (Edition $e) => Scrutin::factory()->for($e->phase(1))->create());
        $edition->phase(1)->scrutins()->update(['statut' => StatutScrutin::Annule]);

        $this->attendreErreur(fn () => $this->ouvrir($edition->phase(1)), 'aucun scrutin');
    }

    /**
     * R17, R18, R53 : les trois phases s'enchaînent dans l'ordre ; la clôture de la finale clôture l'édition.
     */
    public function test_r18_deroule_les_trois_phases_dans_l_ordre_jusqu_a_la_cloture_de_l_edition(): void
    {
        $edition = $this->editionLancee(function (Edition $e): void {
            Scrutin::factory()->for($e->phase(Phase::PRIMAIRES))->planifie()->create();
            Scrutin::factory()->for($e->phase(Phase::PRIMAIRES))->statut(StatutScrutin::Annule)->create();
        });

        foreach ([Phase::PRIMAIRES, Phase::DEMI_FINALES, Phase::FINALE] as $numero) {
            $phase = $edition->phase($numero);
            $scrutin = $numero === Phase::PRIMAIRES
                ? $phase->scrutins()->where('statut', '!=', StatutScrutin::Annule->value)->sole()
                : Scrutin::factory()->for($phase)->planifie()->create();

            $this->ouvrir($phase);
            $this->assertSame(StatutPhase::Ouverte, $phase->fresh()->statut);
            $this->assertNotNull($phase->fresh()->date_ouverture);

            $scrutin->update(['statut' => StatutScrutin::ResultatsPublies]);
            app(CloturerPhase::class)->handle($phase);
            $this->assertSame(StatutPhase::Cloturee, $phase->fresh()->statut);
        }

        $this->assertSame(StatutEdition::Cloturee, $edition->fresh()->statut);
    }

    private function editionPlanifiee(): Edition
    {
        $edition = Edition::factory()->avecPhases()->create([
            'annee' => 2027,
            'date_debut' => '2027-03-01',
            'date_fin' => '2027-04-30',
            'date_gel' => '2027-02-15',
        ]);

        foreach ([1 => ['2027-03-01', '2027-03-15'], 2 => ['2027-03-20', '2027-04-05'], 3 => ['2027-04-10', '2027-04-30']] as $numero => [$debut, $fin]) {
            $edition->phase($numero)->update(['date_debut' => $debut, 'date_fin' => $fin]);
        }

        return $edition;
    }

    /**
     * Édition planifiée puis lancée : $preparer génère les scrutins de la phase 1, exigés au lancement.
     */
    private function editionLancee(callable $preparer): Edition
    {
        $edition = $this->editionPlanifiee();
        $preparer($edition);

        return app(LancerEdition::class)->handle($edition)->load('phases');
    }

    private function ouvrir(Phase $phase): Phase
    {
        return app(OuvrirPhase::class)->handle($phase);
    }

    private function attendreErreur(callable $action, string $extrait): void
    {
        try {
            $action();
            $this->fail('Une erreur de validation était attendue.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($extrait, implode(' ', array_merge(...array_values($exception->errors()))));
        }
    }
}
