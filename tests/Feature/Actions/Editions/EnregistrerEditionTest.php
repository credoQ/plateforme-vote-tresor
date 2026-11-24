<?php

namespace Tests\Feature\Actions\Editions;

use App\Actions\Editions\EnregistrerEdition;
use App\Enums\Editions\Decoupage;
use App\Enums\Editions\StatutEdition;
use App\Enums\Editions\StatutPhase;
use App\Enums\Referentiel\CodePoleElectoral;
use App\Enums\Scrutins\OrigineCandidate;
use App\Models\Edition;
use App\Models\Scrutin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EnregistrerEditionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * R16, R17 : sans paramétrage, une édition est créée en préparation avec l'organisation par défaut en trois phases.
     */
    public function test_r17_cree_une_edition_avec_l_organisation_par_defaut(): void
    {
        $edition = $this->enregistrer($this->donnees());

        $this->assertSame(StatutEdition::EnPreparation, $edition->statut);
        $this->assertFalse($edition->vote_blanc_autorise);
        $this->assertNull($edition->quorum);
        $this->assertSame([1, 2, 3], $edition->phases->pluck('numero')->all());
        $this->assertSame(['Phase 1 · primaires', 'Phase 2 · demi-finales', 'Phase 3 · finale'], $edition->phases->pluck('libelle')->all());
        $this->assertTrue($edition->phases->every(fn ($phase) => $phase->statut === StatutPhase::Planifiee));
        $this->assertSame([2, 2, 1], $edition->phases->map(fn ($phase) => $phase->colleges->count())->all());
        $finale = $edition->phases->last()->colleges->sole();
        $this->assertNull($finale->pole_electoral_id);
        $this->assertSame([Decoupage::Ensemble, OrigineCandidate::Qualification, 1], [$finale->decoupage, $finale->origine_candidates, $finale->nb_elues]);
    }

    /**
     * R17, R20, R28, R52 : le comité paramètre ses phases à la création, avec leurs collèges, l'origine des candidates et le nombre d'élues.
     */
    public function test_r17_cree_une_edition_avec_ses_phases_et_ses_colleges(): void
    {
        $edition = $this->enregistrer($this->donnees(['phases' => [
            1 => ['libelle' => 'Élections départementales', 'colleges' => [
                'DEPARTEMENTAL' => ['decoupage' => 'PAR_CIRCONSCRIPTION', 'origine' => 'NOMINATION', 'nb_elues' => 1],
            ]],
            2 => ['libelle' => 'Élections finales', 'colleges' => [
                'DEPARTEMENTAL' => ['decoupage' => 'ENSEMBLE', 'origine' => 'QUALIFICATION', 'nb_elues' => 1],
                'STRUCTURES' => ['decoupage' => 'ENSEMBLE', 'origine' => 'NOMINATION', 'nb_elues' => 3],
            ]],
        ]]));

        $this->assertSame(['Élections départementales', 'Élections finales'], $edition->phases->pluck('libelle')->all());
        $central = $edition->phase(2)->colleges->firstWhere('poleElectoral.code', CodePoleElectoral::Structures);
        $this->assertSame([Decoupage::Ensemble, OrigineCandidate::Nomination, 3], [$central->decoupage, $central->origine_candidates, $central->nb_elues]);
    }

    /**
     * R17, R26, R35 : les phases se suivent, le collège de toute la DGTCP est seul, la qualification suit un collège du même pôle.
     */
    public function test_r17_rejette_une_organisation_incoherente(): void
    {
        $college = fn (string $origine = 'AUTOMATIQUE', string $decoupage = 'ENSEMBLE'): array => ['decoupage' => $decoupage, 'origine' => $origine, 'nb_elues' => 1];

        $incoherentes = [
            'phase 2 sans phase 1' => [2 => ['libelle' => 'P2', 'colleges' => ['DGTCP' => $college()]]],
            'qualification en phase 1' => [1 => ['libelle' => 'P1', 'colleges' => ['DGTCP' => $college('QUALIFICATION')]]],
            'DGTCP combinée' => [1 => ['libelle' => 'P1', 'colleges' => ['DGTCP' => $college(), 'STRUCTURES' => $college()]]],
            'DGTCP par circonscription' => [1 => ['libelle' => 'P1', 'colleges' => ['DGTCP' => $college('AUTOMATIQUE', 'PAR_CIRCONSCRIPTION')]]],
            'qualification sans le même pôle' => [
                1 => ['libelle' => 'P1', 'colleges' => ['DEPARTEMENTAL' => $college()]],
                2 => ['libelle' => 'P2', 'colleges' => ['STRUCTURES' => $college('QUALIFICATION')]],
            ],
            'phase sans collège' => [1 => ['libelle' => 'P1', 'colleges' => []]],
            'quatre phases' => array_fill(1, 4, ['libelle' => 'P', 'colleges' => ['DGTCP' => $college()]]),
        ];

        foreach ($incoherentes as $cas => $phases) {
            $this->attendreErreur('phases', fn () => $this->enregistrer($this->donnees(['phases' => $phases])), $cas);
        }
        $this->assertSame(0, Edition::count());
    }

    /**
     * R17 : les phases se reparamètrent tant qu'aucun scrutin n'est généré ; les dates d'une phase conservée restent.
     */
    public function test_r17_reparametre_les_phases_tant_qu_aucun_scrutin_n_est_genere(): void
    {
        $edition = $this->enregistrer($this->donnees());
        $edition->phase(1)->update(['date_debut' => '2027-03-01', 'date_fin' => '2027-03-10']);
        $unePhase = [1 => ['libelle' => 'Phase unique', 'colleges' => ['DGTCP' => ['decoupage' => 'ENSEMBLE', 'origine' => 'NOMINATION', 'nb_elues' => 2]]]];

        $this->enregistrer($this->donnees(['phases' => $unePhase]), $edition);

        $edition->refresh()->load('phases.colleges');
        $this->assertSame(['Phase unique'], $edition->phases->pluck('libelle')->all());
        $this->assertSame('2027-03-10', $edition->phase(1)->date_fin->toDateString());
        $this->assertSame(2, $edition->phase(1)->colleges->sole()->nb_elues);

        Scrutin::factory()->for($edition->phase(1))->create();
        $this->attendreErreur('phases', fn () => $this->enregistrer($this->donnees(['phases' => $unePhase]), $edition->fresh()));
    }

    public function test_parametre_le_vote_blanc_et_le_quorum_de_l_edition(): void
    {
        $edition = $this->enregistrer($this->donnees(['vote_blanc_autorise' => true, 'quorum' => 30]));

        $this->assertTrue($edition->fresh()->vote_blanc_autorise);
        $this->assertSame('30.00', $edition->fresh()->quorum);
    }

    /**
     * R16 : une édition par année.
     */
    public function test_r16_rejette_une_seconde_edition_pour_la_meme_annee(): void
    {
        $this->enregistrer($this->donnees());

        $this->attendreErreur('annee', fn () => $this->enregistrer($this->donnees()));
    }

    /**
     * R14 : le gel peut tomber pendant l'édition, jamais après sa fin ni au début d'une phase déjà planifiée.
     */
    /**
     * R16 : la date de gel ne se saisit pas ; elle est fixée au premier arrêt des listes de l'édition.
     */
    public function test_r16_l_edition_se_cree_sans_date_de_gel(): void
    {
        $edition = $this->enregistrer([...$this->donnees(), 'date_gel' => '2027-02-15']);

        $this->assertNull($edition->fresh()->date_gel);
        $this->assertFalse($edition->fresh()->listesSontGelees());
    }

    public function test_rejette_un_calendrier_qui_commence_avant_l_annee_de_l_edition(): void
    {
        $this->attendreErreur('date_debut', fn () => $this->enregistrer($this->donnees(['date_debut' => '2026-12-15'])));
    }

    public function test_accepte_un_calendrier_qui_deborde_sur_l_annee_suivante(): void
    {
        $edition = $this->enregistrer($this->donnees(['date_debut' => '2027-12-01', 'date_fin' => '2028-02-28']));

        $this->assertSame('2028-02-28', $edition->date_fin->toDateString());
    }

    public function test_modifie_une_edition_en_preparation_sans_recreer_ses_phases(): void
    {
        $edition = $this->enregistrer($this->donnees());

        $this->enregistrer($this->donnees(['libelle' => 'Édition 2027 modifiée']), $edition);

        $this->assertSame('Édition 2027 modifiée', $edition->fresh()->libelle);
        $this->assertCount(3, $edition->fresh()->phases);
    }

    public function test_refuse_de_modifier_une_edition_lancee(): void
    {
        $edition = Edition::factory()->enCours()->create();

        $this->attendreErreur('edition', fn () => $this->enregistrer($this->donnees(), $edition));
    }

    /**
     * @param  array<string, mixed>  $surcharge
     * @return array<string, mixed>
     */
    private function donnees(array $surcharge = []): array
    {
        return [
            'annee' => 2027,
            'libelle' => 'Femme modèle du Trésor 2027',
            'date_debut' => '2027-03-01',
            'date_fin' => '2027-04-30',
            ...$surcharge,
        ];
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    private function enregistrer(array $donnees, ?Edition $edition = null): Edition
    {
        return app(EnregistrerEdition::class)->handle($donnees, $edition);
    }

    private function attendreErreur(string $champ, callable $action, string $cas = ''): void
    {
        try {
            $action();
            $this->fail("Une erreur de validation était attendue sur « {$champ} » {$cas}.");
        } catch (ValidationException $exception) {
            $this->assertNotEmpty(array_filter(array_keys($exception->errors()), fn (string $cle): bool => str_starts_with($cle, $champ)), "Erreur attendue sur « {$champ} » {$cas}.");
        }
    }
}
