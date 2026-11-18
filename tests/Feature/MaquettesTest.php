<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MaquettesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_la_racine_redirige_vers_les_maquettes(): void
    {
        $this->get('/')->assertRedirect('/maquettes');
    }

    public function test_l_index_des_maquettes_s_affiche(): void
    {
        $this->get(route('maquettes.index'))->assertOk()->assertSee('Maquettes de la plateforme');
    }

    #[DataProvider('pages')]
    public function test_chaque_maquette_s_affiche(string $page, string $texte): void
    {
        $this->get(route('maquettes.page', $page))->assertOk()->assertSee($texte);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function pages(): array
    {
        return [
            'connexion' => ['connexion', 'Se connecter'],
            'tableau de bord' => ['tableau-de-bord', 'Tableau de bord du comité'],
            'vote' => ['vote', 'Confirmer votre vote'],
            'candidates' => ['candidates', 'Désactiver la candidate'],
            'résultats' => ['resultats', 'Lauréate départementale'],
            'tokens' => ['tokens', 'Échelle du bleu Trésor'],
        ];
    }

    public function test_une_page_inconnue_renvoie_404(): void
    {
        $this->get('/maquettes/inexistante')->assertNotFound();
    }
}
