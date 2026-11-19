<?php

namespace Tests\Feature\Suivi;

use App\Models\Edition;
use App\Notifications\CodeDeConnexion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Tests\TestCase;

/**
 * Gabarit commun des e-mails : emblème du Trésor, bande tricolore, édition en cours et pied de page de la DGTCP.
 */
class GabaritEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_e_mails_portent_l_embleme_l_edition_en_cours_et_le_pied_de_page_dgtcp(): void
    {
        Edition::factory()->enCours()->create(['annee' => 2026]);

        $html = (string) (new CodeDeConnexion('482917', 10))->toMail(new AnonymousNotifiable)->render();

        $this->assertStringContainsString(asset('images/embleme-tresor.png'), $html);
        $this->assertStringContainsString('tricolore-vert', $html);
        $this->assertStringContainsString('Édition 2026', $html);
        $this->assertStringContainsString('© DIA/DGTCP', $html);
        $this->assertStringNotContainsString('Laravel', $html);
    }

    public function test_sans_edition_en_cours_l_en_tete_n_affiche_pas_d_edition(): void
    {
        $html = (string) (new CodeDeConnexion('482917', 10))->toMail(new AnonymousNotifiable)->render();

        $this->assertStringNotContainsString('Édition', $html);
    }
}
