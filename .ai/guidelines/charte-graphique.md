# Charte graphique

## Source

- Charte Kadoo 2.0, recolorée avec la charte graphique de la DGTCP : bleu Trésor `#0054A0` (primaire, charte DGTCP), accent or doré `#E0A800`, vert Trésor `#2E5A21` (deuxième couleur des graphiques). Le jaune Trésor `#F4E800` n'est pas utilisé dans l'interface.
- Le détail est dans `design/README.md`. Les tokens de référence sont dans `design/tokens/tresor-tokens.json`.
- Les fichiers de styles sont dans `resources/css/design/` :
  - `tresor-tokens.css` : variables `--tr-*` des thèmes clair et sombre ;
  - `tresor-theme.css` : thème Tailwind ;
  - `composants.css` : composants ;
  - `polices.css` : déclaration de Montserrat.
- Les maquettes de référence sont dans `resources/views/maquettes/`, accessibles par l'URL `/maquettes`.

## Règles

- N'écrivez jamais une couleur en dur :
  - pour les rôles, utilisez les variables `var(--tr-primaire)`, `var(--tr-texte-2)`, `var(--tr-surface)`, etc. ;
  - pour les niveaux d'échelle, utilisez les classes Tailwind du thème (`bg-tresor-600`, `text-gris-900`, `bg-surface`).
- Réutilisez les composants de `composants.css` (`.btn-primaire`, `.carte`, `.tuile`, `.badge--succes`, `.tableau`, `.modale`…) avant d'en créer. Un nouveau composant s'ajoute d'abord dans `composants.css`, puis dans la page `maquettes/tokens`.
- Un seul bouton primaire par zone. Le bouton primaire porte le liseré or de 3 px (`--tr-accent-sur-primaire`). Le rouge plein est réservé aux actions destructrices confirmées.
- Un état ne s'exprime jamais par la seule couleur : un badge porte toujours une pastille et un libellé exact.
- L'or (`--tr-accent`) marque (menu actif, anneau de l'avatar, barre de la lauréate) et n'est jamais une couleur de texte sur fond clair ; le badge « Lauréate » (`.badge--accent`) utilise `or-50` et `or-700`. Sur un fond primaire, utilisez `--tr-accent-sur-primaire`. Le tricolore n'apparaît qu'en bande de 3 px.
- Sur un fond `--tr-primaire`, le texte utilise `--tr-sur-primaire`, jamais `#fff` : il devient sombre en thème sombre.
- Tableaux :
  - lignes de 52 px ;
  - nombres alignés à droite en chiffres tabulaires ;
  - au plus quatre filtres visibles ;
  - au plus quatre actions par ligne.
- Graphiques : palette `--tr-graph-1` à `--tr-graph-6` dans l'ordre fixe. Une couleur suit l'entité, pas son rang.
- Icônes : Lucide uniquement (`<i data-lucide="nom"></i>`). Une action garde toujours la même icône.
- Police : Montserrat, servie en local. N'ajoutez aucune police ni aucun CDN distant.
- Chaque écran doit fonctionner en thème sombre (`data-theme="sombre"`) et respecter le contraste AA : vérifiez toute nouvelle paire de couleurs de texte.
