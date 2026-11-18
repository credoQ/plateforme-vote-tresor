<?php

namespace Tests\Feature\Actions\Editions;

use App\Actions\Editions\EnregistrerEdition;
use App\Enums\Editions\StatutEdition;
use App\Enums\Editions\StatutPhase;
use App\Models\Edition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EnregistrerEditionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * R16, R17 : une édition est créée en préparation, avec ses trois phases ordonnées.
     */
    public function test_r17_cree_une_edition_avec_ses_trois_phases(): void
    {
        $edition = $this->enregistrer($this->donnees());

        $this->assertSame(StatutEdition::EnPreparation, $edition->statut);
        $this->assertFalse($edition->vote_blanc_autorise);
        $this->assertNull($edition->quorum);
        $this->assertSame([1, 2, 3], $edition->phases->pluck('numero')->all());
        $this->assertSame(['Phase 1 · primaires', 'Phase 2 · demi-finales', 'Phase 3 · finale'], $edition->phases->pluck('libelle')->all());
        $this->assertTrue($edition->phases->every(fn ($phase) => $phase->statut === StatutPhase::Planifiee && $phase->nb_qualifiees === 1));
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

    public function test_rejette_un_gel_des_listes_apres_le_debut_de_l_edition(): void
    {
        $this->attendreErreur('date_gel', fn () => $this->enregistrer($this->donnees(['date_gel' => '2027-03-10'])));
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
            'date_gel' => '2027-02-15',
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

    private function attendreErreur(string $champ, callable $action): void
    {
        try {
            $action();
            $this->fail("Une erreur de validation était attendue sur « {$champ} ».");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($champ, $exception->errors());
        }
    }
}
