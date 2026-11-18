# Directives pour les agents

Laravel Boost est installé. Les directives complètes sont dans `CLAUDE.md`. Ce fichier est généré par `php artisan boost:install` : ne le modifiez pas à la main.

`CLAUDE.md` est composé de deux sources :

- les directives propres au projet, dans `.ai/guidelines/` :
  - `projet.md` : contexte métier et règles du vote ;
  - `architecture.md` : pile technique et conventions ;
  - `charte-graphique.md` : charte Kadoo en bleu Trésor ;
- les règles Laravel, PHP, Pint et PHPUnit fournies par Boost.

Avant de modifier un fichier, lisez `.ai/rules/index.md`, puis chaque règle dont les chemins couvrent ce fichier :

- `vote.md` : secret et intégrité du vote ;
- `base-de-donnees.md` : base de données ;
- `vues.md` : vues et styles.

Après avoir modifié `.ai/guidelines/`, régénérez `CLAUDE.md` :

```bash
php artisan boost:install --guidelines --skills --mcp --no-interaction
```

Après avoir modifié `.ai/rules/`, régénérez l'index :

```bash
php artisan boost:index-rules
```
