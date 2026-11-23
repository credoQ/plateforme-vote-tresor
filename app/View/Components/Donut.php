<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Donut en SVG : chaque segment devient un arc proportionnel à sa valeur, dans l'ordre donné. La couleur est le rang
 * dans la palette des graphiques (1 à 6 : --tr-graph-N), 0 pour la part neutre (abstention, reste).
 * <x-donut :segments="[['libelle' => 'Votants', 'valeur' => 120, 'couleur' => 1]]" valeur="49,7 %" legende="votants" />
 */
class Donut extends Component
{
    /**
     * Arcs prêts à tracer : part du cercle (sur 100) et décalage depuis le haut.
     *
     * @var list<array{libelle: string, valeur: int|float, couleur: int, part: float, decalage: float}>
     */
    public array $arcs = [];

    /**
     * Texte lu par les lecteurs d'écran : « Votants : 120, Abstention : 80 ».
     */
    public string $description;

    /**
     * @param  list<array{libelle: string, valeur: int|float, couleur: int}>  $segments
     */
    public function __construct(array $segments, public string $valeur, public ?string $legende = null, public bool $petit = false)
    {
        $total = array_sum(array_column($segments, 'valeur'));
        $cumul = 0.0;

        foreach ($segments as $segment) {
            $part = $total > 0 ? $segment['valeur'] * 100 / $total : 0.0;
            $this->arcs[] = [...$segment, 'part' => round($part, 3), 'decalage' => round(-$cumul, 3)];
            $cumul += $part;
        }

        $this->description = implode(', ', array_map(fn (array $s): string => $s['libelle'].' : '.$s['valeur'], $segments));
    }

    public function render(): View
    {
        return view('components.donut');
    }
}
