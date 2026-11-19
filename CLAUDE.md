<laravel-boost-guidelines>
=== .ai/architecture rules ===

# Architecture et pile technique

## Pile

- Monolithe Laravel 13 (PHP 8.5), base PostgreSQL, interface Blade avec Tailwind CSS 4 et Vite 8, icônes Lucide.
- Le front reste dans le monolithe : vues Blade et composants anonymes (`resources/views/components`). N'ajoutez pas de framework JavaScript (Vue, React, Livewire, Inertia) sans accord.
- Les traitements longs (dépouillement, génération des procès-verbaux, envoi de notifications) passent par des jobs en file d'attente. L'ouverture et la clôture des scrutins passent par le planificateur.

## Organisation du code

- Monolithe découpé par domaine métier : Référentiel, Agents, Éditions, Scrutins, Vote, Résultats, Audit. Placez la logique métier dans des classes d'action ou de service (`app/Actions/<Domaine>/…`), pas dans les contrôleurs ni dans les vues.
- Valeurs fermées (statuts, origines, pôles) : enums PHP adossés à des chaînes, avec des clés en TitleCase (`StatutScrutin::Ouvert`).
- Toute opération qui écrit plusieurs lignes ou tables (y compris l'entrée du journal d'audit) se fait dans `DB::transaction()` : tout est enregistré, ou rien. Une écriture conditionnée par un statut (ouverture, arrêt, désistement, planification) relit la ligne avec `lockForUpdate()` dans la transaction avant d'écrire.
- Paramètres d'édition encore à valider par le métier (vote blanc, quorum, nombre de qualifiées, départage) : colonnes ou configuration, jamais des constantes dans le code.

## Base de données

- Noms de tables et de colonnes en français, au pluriel et en snake_case, alignés sur le MCD : `editions`, `phases`, `scrutins`, `structures`, `types_structure`, `poles_electoraux`, `agents`, `affectations`, `inscriptions`, `candidates`, `bulletins`, `proces_verbaux`, `journal_audit`.
- Créez les migrations avec `php artisan make:migration`. Utilisez des clés étrangères contraintes et des index sur les colonnes de recherche (`matricule`, `scrutin_id`).

## Qualité

- Tests de fonctionnalité PHPUnit pour chaque règle de gestion implémentée, en citant la règle (`R40`) dans le nom ou le docblock du test.
- Formatez avec Pint (`vendor/bin/pint --dirty`) avant de terminer une tâche.

=== .ai/charte-graphique rules ===

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

=== .ai/git rules ===

# Git

## Branches

- `main` : version stable. On n'y travaille jamais directement.
- `dev` : branche d'intégration. Tout le travail y est fusionné.
- Pour travailler sur un module, créez une branche depuis `dev`, nommée d'après le fichier du module dans `docs/fonctionnel/modules/` (par exemple `module/06-vote`, `module/02-referentiel-des-structures`).
- Quand le travail du module est terminé, que les tests passent et que Pint est appliqué, fusionnez la branche dans `dev` :

```bash
git switch dev
git merge --no-ff module/06-vote
```

- Une modification qui ne relève d'aucun module (configuration, charte, documentation générale) se fait sur `dev`.

## Messages de commit

- Une seule ligne courte, en français, à l'impératif ou au présent : « Ajoute le bulletin de vote », « Corrige le contraste du badge ». Pas de corps de message détaillé, pas de liste.
- N'utilisez jamais de tiret cadratin (—) ni de tiret demi-cadratin (–) dans un message de commit.
- N'ajoutez aucune ligne de co-auteur (`Co-Authored-By`) ni aucune autre mention en pied de message : le message tient sur sa seule ligne.

=== .ai/projet rules ===

# Projet : vote de la Femme modèle du Trésor

## Contexte

- Plateforme de vote électronique de la Direction générale du Trésor et de la Comptabilité publique (DGTCP, Bénin). Chaque édition annuelle désigne la « Femme modèle du Trésor ».
- Références fonctionnelles, dans le dépôt :
  - `docs/fonctionnel/regles-de-gestion.md` : règles R1 à R59 ;
  - `docs/fonctionnel/modules/*.md` : fonctionnalités de chaque module, avec ses acteurs, ses entités et ses règles ;
  - le document technique Word (MCD, cas d'utilisation), hors du dépôt.
- En cas de doute sur une règle métier, lisez ces fichiers plutôt que de supposer. Citez le numéro de la règle (`R40`) dans le code et les tests concernés.
- Quand une fonctionnalité est livrée, cochez sa case dans le fichier du module.
- Les « Points à valider et questions à éclaircir » (fin de `regles-de-gestion.md`) ne sont pas tranchés : ne les codez pas en dur, rendez-les paramétrables ou demandez.

## Langue

- L'interface, les messages, les libellés de validation et les e-mails sont en français (`APP_LOCALE=fr`).
- Le vocabulaire métier reste en français dans le code (classes, tables, colonnes, routes) pour coller au MCD : `Edition`, `Phase`, `Scrutin`, `Candidate`, `Bulletin`, `ProcesVerbal`, `Structure`, `TypeStructure`, `PoleElectoral`, `Departement`, `Agent`. Les mots techniques Laravel (controller, request, policy, job) restent en anglais.

## Déroulement électoral

- Une édition comporte une à trois phases, ouvertes dans l'ordre et paramétrées par le comité à sa création (`OrganiserPhases`). Chaque phase déclare ses collèges (table `colleges`) : le pôle départemental, le pôle structures, ou l'ensemble de la DGTCP (seul dans sa phase). Chaque collège fixe :
  1. le découpage : un scrutin par circonscription (département, structure électorale) ou un seul scrutin pour tout le collège ;
  2. l'origine des candidates : d'office, nomination par le comité, ou qualification depuis la phase précédente ;
  3. le nombre d'élues par scrutin.
- L'organisation par défaut reprend les trois phases historiques : primaires (par département et par structure, candidates d'office), demi-finales (un scrutin par pôle), finale (toute la DGTCP). Le paramétrage est figé dès que des scrutins sont générés. Ne codez jamais une phase par son numéro : lisez ses collèges.
- Une phase ne s'ouvre que si tous les résultats de la phase précédente sont validés et publiés.
- Un agent vote au plus une fois par phase, dans la circonscription de son poste d'affectation. Les agents d'une structure centrale en poste dans une TD (antennes de la DIA, par exemple) votent avec leur département.
- Il n'y a pas de dépôt de candidature : selon le collège, les candidates sont d'office (toutes les agents de sexe féminin en service dans la circonscription à la date de gel), nominées par le comité, ou les lauréates qualifiées de la phase précédente. Les femmes modèles sont les lauréates de la dernière phase.
- Pour un collège aux candidates d'office, aucun scrutin n'est créé pour une circonscription sans agent femme en service : ses agents ne votent pas dans cette phase.
- Une candidate peut être désactivée (désistement par elle-même, ou par le point focal ou le comité à sa demande) jusqu'à l'ouverture du scrutin. Chaque désactivation et chaque réactivation sont tracées.
- « En service » signifie : figurer dans la liste des agents avec une affectation en cours. Les champs `date_prise_service`, `telephone`, `email`, `fonction` et `statut` de l'agent sont informatifs : aucune règle ne doit s'appuyer dessus.
- Hors périmètre : le dépôt et la validation de dossiers de candidature, l'évaluation des candidates (grille de notation), les réclamations et le calendrier opérationnel du métier. Ne pas les implémenter. La nomination se limite à la saisie, par le comité, d'une décision déjà prise.

## Notifications

- Les notifications (annonces, codes de connexion) sont envoyées uniquement par e-mail. Pas de SMS ni de passerelle tierce.
- Tout envoi d'e-mail est asynchrone : chaque notification implémente `ShouldQueue` (jamais `notifyNow`, jamais `Mail::send` direct), avec ses tentatives et son délai de reprise. Files : `urgent` pour les codes de connexion (job chiffré avec `ShouldBeEncrypted`, abandonné après la validité du code), `mails` pour les annonces.
- En production, le processus de file traite les files par priorité : `php artisan queue:work --queue=urgent,mails,default`. Les échecs se relancent depuis l'écran « Tâches en échec ».
- L'adresse utilisée est celle du compte, sinon celle de la fiche agent ; un agent sans adresse voit les annonces sur la plateforme.

## Authentification

- Connexion sans mot de passe (R42) : matricule, puis code à usage unique envoyé par e-mail (`app/Actions/Acces/DemanderCodeConnexion.php`, `VerifierCodeConnexion.php`, `ConnexionController`). N'ajoutez ni mot de passe, ni paquet d'authentification (Fortify, Breeze…).
- L'adresse utilisée est `User::adresseDeConnexion()` : celle du compte, sinon celle de la fiche agent. Un compte sans adresse ne peut pas se connecter.
- Les connexions, échecs et déconnexions sont journalisés par les écouteurs des événements `Login`, `Failed` et `Logout` (`AppServiceProvider`).

## Profils

Administrateur, Comité d'organisation, Point focal (limité à sa structure), Électeur, Observateur (lecture seule). Contrôlez les droits avec des Policies et des Gates, jamais seulement dans les vues.

=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
