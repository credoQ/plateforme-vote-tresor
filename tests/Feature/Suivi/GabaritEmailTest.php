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
        $this->assertStringContainsString('© '.date('Y').' DIA/DGTCP', $html);
        $this->assertStringContainsString('chargé des Finances et de la Microfinance', $html);
        $this->assertStringNotContainsString('Laravel', $html);
    }

    public function test_le_code_de_connexion_est_mis_en_avant_dans_son_bloc_avec_sa_validite(): void
    {
        $html = (string) (new CodeDeConnexion('482917', 10))->toMail(new AnonymousNotifiable)->render();

        $this->assertMatchesRegularExpression('/class="code-valeur"[^>]*>\s*482917\s*</', $html);
        $this->assertStringContainsString('Valable 10 minutes', $html);
        $this->assertStringContainsString(asset('fonts/montserrat/Montserrat-Regular.ttf'), $html);
    }

    public function test_sans_edition_en_cours_l_en_tete_n_affiche_pas_d_edition(): void
    {
        $html = (string) (new CodeDeConnexion('482917', 10))->toMail(new AnonymousNotifiable)->render();

        $this->assertStringNotContainsString('Édition', $html);
    }
}
