# Charte graphique · Femme modèle du Trésor

Charte **Kadoo 2.0** (DGTCP), recolorée avec les couleurs de la **charte graphique de la DGTCP** :

- **bleu Trésor** `#0054A0` (Pantone 3015 C) : couleur primaire, à la place du vert Kadoo ;
- **jaune Trésor** `#F4E800` (Pantone 803 C) : accent, à la place de l'or Kadoo ;
- **vert Trésor** `#2E5A21` (Pantone 364 C) : deuxième couleur des graphiques.

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
- **Accent :** famille `jaune-50` à `jaune-700`, construite avec les mêmes proportions que l'or Kadoo. Le jaune marque (liseré du bouton primaire, menu actif, anneau de l'avatar) ; il n'est jamais un fond de texte, sauf `jaune-50` pour le badge « Lauréate ».
- **Sur la primaire :** rôle `--tr-sur-primaire`, blanc en thème clair et presque noir en thème sombre, où le bleu éclairci ne permettrait pas un texte blanc lisible.
- **Neutres :** ils gardent la luminosité des gris Kadoo, avec une légère teinte bleue au lieu de verte.
- **Graphiques :** le rang 1 est le bleu Trésor et le rang 2 le vert Trésor (l'ancien bleu du rang 2 était trop proche de la primaire).
- **Inchangés :** les couleurs d'état, le tricolore, la typographie (Montserrat), les rayons, les ombres et la structure d'écran.

La charte DGTCP préconise Bookman Old Style, Arial et Myriad Pro pour les documents. L'interface garde Montserrat, la police de la charte Kadoo.

Contrastes vérifiés au niveau AA au minimum :

- primaire sur blanc : 7,57:1 ;
- texte principal : 16,65:1 ;
- placeholder : 4,94:1 ;
- badge jaune : 5,73:1 ;
- primaire en thème sombre : 4,61:1.

## Régénérer les tokens

```bash
python design/tokens/generer_tokens.py design/tokens/source-kadoo/kadoo-tokens.json design/tokens/sortie "#0054A0" "#F4E800" "#2E5A21"
```

Copiez ensuite les fichiers `.css` générés dans `resources/css/design/` et le `.json` dans `design/tokens/`, puis lancez `npm run build`.

## Maquettes

Lancez `php artisan serve`, puis ouvrez `/maquettes`. Six pages sont disponibles : connexion, tableau de bord du comité, bulletin de vote, candidates d'office, résultats, tokens. Les données sont fictives. Les routes `/maquettes` sont à retirer avant la mise en production.
