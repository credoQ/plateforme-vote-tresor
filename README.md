# Plateforme de vote de la Femme modèle du Trésor

Plateforme de vote électronique de la Direction générale du Trésor et de la Comptabilité publique (DGTCP, Bénin). Chaque édition désigne la Femme modèle du Trésor en trois phases : primaires par département et par structure, demi-finales par pôle, finale ouverte à tous les agents.

Monolithe Laravel 13 (PHP 8.5), PostgreSQL, Blade, Tailwind CSS 4 et Vite.

## Documentation

| Sujet | Fichier |
| --- | --- |
| Règles de gestion R1 à R57, points à valider | [docs/fonctionnel/regles-de-gestion.md](docs/fonctionnel/regles-de-gestion.md) |
| Modules, fonctionnalités, écrans et avancement | [docs/fonctionnel/README.md](docs/fonctionnel/README.md) |
| Charte graphique et tokens | [design/README.md](design/README.md) |
| Conventions du projet (architecture, git, vues, base) | [.ai/guidelines](.ai/guidelines) et [.ai/rules](.ai/rules) |

## Installation

Prérequis : PHP 8.5 avec `pdo_pgsql`, Composer, Node.js, PostgreSQL.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Renseignez dans `.env` la base PostgreSQL (`DB_*`), l'adresse publique (`APP_URL`, reprise dans les QR codes des PDF) et le serveur de messagerie (`MAIL_*`) : la connexion se fait par un code envoyé par e-mail. Puis :

```bash
php artisan migrate --seed
php artisan acces:creer-administrateur admin "Nom de l'administrateur" admin@exemple.bj
```

## Lancer en développement

```bash
composer run dev
```

La commande lance le serveur, Vite, le processus de file d’attente (files `urgent`, `mails`, `default`) et le planificateur.

## Production

Trois processus doivent tourner en permanence :

| Processus | Commande | Rôle |
| --- | --- | --- |
| Serveur web | PHP-FPM derrière Nginx ou Apache | Écrans |
| File d'attente | `php artisan queue:work --queue=urgent,mails,default` | E-mails (codes de connexion, annonces) et procès-verbaux |
| Planificateur | `php artisan schedule:run` chaque minute (cron) | Ouverture et fermeture des scrutins à leur date-heure |

Sans le processus de file, aucun e-mail ne part et personne ne peut se connecter. Les tâches en échec se relancent depuis l'écran « Tâches en échec » (administrateur).

## Commandes utiles

| Commande | Usage |
| --- | --- |
| `php artisan agents:importer agents.xlsx` | Importe ou met à jour les agents et leurs affectations (Excel ou CSV) |
| `php artisan acces:generer-comptes` | Crée les comptes électeurs des agents en service et liste ceux sans adresse e-mail |
| `php artisan acces:creer-administrateur login nom email` | Crée un compte administrateur |
| `php artisan scrutins:basculer` | Ouvre et ferme les scrutins arrivés à échéance (lancée par le planificateur) |

## Tests

Les tests tournent sur une base PostgreSQL dédiée, `vote_femme_modele_test` (voir `phpunit.xml`) :

```bash
php artisan test
```

## Organisation du travail

- `main` : version stable ; `dev` : intégration ; une branche `module/NN-nom` par module, fusionnée dans `dev` (voir [.ai/guidelines/git.md](.ai/guidelines/git.md)).
- Messages de commit : une ligne courte, en français.
