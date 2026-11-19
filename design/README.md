# Charte graphique · Femme modèle du Trésor

Charte **Kadoo 2.0** (DGTCP), recolorée :

- **bleu Trésor** `#0054A0` (Pantone 3015 C, charte DGTCP) : couleur primaire, à la place du vert Kadoo ;
- **or doré** `#E0A800` : accent, à la place de l'or Kadoo ; plus chaud et plus soutenu que le jaune de la charte ;
- **vert Trésor** `#2E5A21` (Pantone 364 C, charte DGTCP) : deuxième couleur des graphiques.

Le jaune Trésor `#F4E800` (Pantone 803 C) et le vert ont été essayés comme accent puis écartés : le jaune rendait trop fluo à côté du bleu, le vert trop terne. Le jaune ne subsiste que dans la bande tricolore.

## Fichiers

| Fichier | Rôle |
|---|---|
| `tokens/tresor-tokens.json` | Tokens de référence (couleurs, typographie, rayons, structure) |
| `../resources/css/design/tresor-tokens.css` | Variables CSS `--tr-*` des thèmes clair et sombre (`data-theme="sombre"`) |
| `../resources/css/design/tresor-theme.css` | Thème Tailwind 4 : classes `bg-tresor-600`, `text-gris-900`, `rounded-carte`, `shadow-niveau-1`… |
| `../resources/css/design/composants.css` | Composants : sidebar, en-tête, boutons, champs, tuiles, badges, tableau, modales, bulletin de vote |
| `../resources/css/design/polices.css` | Montserrat servie en local (licence OFL) |
| `tokens/source-kadoo/` | Tokens Kadoo d'origine, conservés comme source |

## Ce qui change par rapport à Kadoo

- **Primaire :** échelle `tresor-25` à `tresor-950`, construite comme celle de Kadoo, par mélange de la base vers le blanc et vers un presque-noir.
- **Accent :** famille `or-50` à `or-700` (base `or-500` = `#E0A800`), construite avec les niveaux et les proportions de l'or Kadoo. L'or marque : liseré du bouton primaire, menu actif, anneau de l'avatar, barre et badge de la lauréate. Comme l'or Kadoo, il n'est jamais une couleur de texte sur fond clair (2,15:1 sur blanc, repère décoratif) ; le badge « Lauréate » utilise `or-50` et `or-700`.
- **Accent sur la primaire :** rôle `--tr-accent-sur-primaire`, calculé pour garder 3:1 sur le bouton : l'or 500 lui-même en thème clair, un or très clair en thème sombre, où le bouton est plus clair.
- **Sur la primaire :** rôle `--tr-sur-primaire`, blanc en thème clair et presque noir en thème sombre, où le bleu éclairci ne permettrait pas un texte blanc lisible.
- **Neutres :** ils gardent la luminosité des gris Kadoo, avec une légère teinte bleue au lieu de verte.
- **Graphiques :** le rang 1 est le bleu Trésor et le rang 2 le vert Trésor (l'ancien bleu du rang 2 était trop proche de la primaire).
- **Inchangés :** les couleurs d'état, le tricolore, la typographie (Montserrat), les rayons, les ombres et la structure d'écran.

La charte DGTCP préconise Bookman Old Style, Arial et Myriad Pro pour les documents. L'interface garde Montserrat, la police de la charte Kadoo.

Contrastes vérifiés au niveau AA au minimum :

- primaire sur blanc : 7,57:1 ;
- texte principal : 16,65:1 ;
- placeholder : 4,94:1 ;
- badge « Lauréate » (or 700 sur or 50) : 7,95:1 ;
- liseré or sur le bouton primaire : 3,52:1 (élément non textuel) ;
- liseré du bouton en thème sombre : 3,34:1 ;
- primaire en thème sombre : 4,61:1.

## Régénérer les tokens

```bash
python design/tokens/generer_tokens.py design/tokens/source-kadoo/kadoo-tokens.json design/tokens/sortie "#0054A0" "#2E5A21" "#E0A800"
```

Copiez ensuite les fichiers `.css` générés dans `resources/css/design/` et le `.json` dans `design/tokens/`, puis lancez `npm run build`.

## Documents PDF

Les PDF (procès-verbal, liste électorale, résultats) reprennent l'en-tête et le bas de page de la quittance électronique de la DGTCP, par le composant `resources/views/components/pdf/document.blade.php` :

- en-tête : bloc du ministère, logo ISO 9001 et bloc DGTCP (`resources/images/pdf/`), puis la bande tricolore ;
- bas de page : QR code de vérification en ligne, mention d'édition (date et auteur), mini-bande tricolore et type de document.

Les couleurs de la bande sont celles du drapeau (vert `#008751`, jaune `#FCD116`, rouge `#E8112D`) ; la police est DejaVu Sans, intégrée à dompdf.

## Maquettes

Lancez `php artisan serve`, puis ouvrez `/maquettes`. Six pages sont disponibles : connexion, tableau de bord du comité, bulletin de vote, candidates d'office, résultats, tokens. Les données sont fictives. Les routes `/maquettes` sont à retirer avant la mise en production.
