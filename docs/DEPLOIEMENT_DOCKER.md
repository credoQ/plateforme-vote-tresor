# Déploiement avec Docker

La plateforme tourne dans huit conteneurs décrits par `compose.yaml` :

| Service | Rôle |
|---|---|
| `caddy` | Entrée publique (ports 80 et 443) : HTTPS avec certificat Let's Encrypt automatique |
| `web` | Nginx : sert les fichiers publics (CSS, JS, polices, images) et passe le reste à PHP |
| `app` | PHP-FPM : exécute Laravel. Migre la base à chaque démarrage |
| `worker-codes` | File `urgent` seule : les codes de connexion partent en quelques secondes, quoi qu'il arrive |
| `worker` | Toutes les files (`urgent`, `mails`, `default`) : annonces, accusés de vote, qualifications, procès-verbaux |
| `scheduler` | Planificateur (`schedule:work`) : ouvre et ferme les scrutins à leur date-heure, chaque minute |
| `db` | PostgreSQL 18. Publié seulement sur `127.0.0.1:5433` du serveur, jamais sur Internet |
| `sauvegarde` | Copie de la base toutes les 6 heures dans `./sauvegardes`, gardée 30 jours |

Fichiers : `Dockerfile`, `compose.yaml`, `docker/nginx.conf`, `docker/Caddyfile`, `docker/php.ini`,
`docker/php-fpm.conf`, `docker/entrypoint.sh` (démarrage de app, des workers et du planificateur),
`docker/sauvegarder.sh`, `.env.production.example`.

Des volumes gardent les données d'une mise à jour à l'autre : `postgres` (la base), `stockage`
(procès-verbaux, sessions, cache) et `caddy_data` (certificats HTTPS). Les sauvegardes sont dans le dossier
`sauvegardes` du dépôt, sur le disque du serveur.

## Première installation sur le serveur

Prérequis : Docker et le plugin Compose installés, l'utilisateur dans le groupe `docker`.

```bash
git clone <adresse du dépôt> ~/femme-modele
cd ~/femme-modele
cp .env.production.example .env
```

Remplir le `.env` (`nano .env`) :

- `SITE_DOMAIN` (le domaine, dont l'enregistrement DNS `A` pointe vers le serveur) et `APP_URL=https://` suivi du domaine ;
- `DB_PASSWORD`, tiré au sort : `openssl rand -base64 32` ;
- `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` : le serveur d'envoi des e-mails. Sans lui,
  aucun code de connexion ne part et personne ne peut se connecter.

Construire les images, puis générer la clé de l'application :

```bash
docker compose build
docker compose run --rm --no-deps --entrypoint php app artisan key:generate --show
```

Copier la clé affichée (`base64:...`) dans `APP_KEY` du `.env`, **et la noter hors du serveur** (voir Sécurité). Démarrer :

```bash
docker compose up -d
docker compose logs -f app
```

Au premier démarrage, `app` migre la base. Il reste à charger le référentiel (structures, pôles, rôles),
à créer le premier administrateur, puis à importer les agents :

```bash
docker compose exec app php artisan db:seed --force
docker compose exec app php artisan acces:creer-administrateur <login> "<Nom affiché>" <email>
docker compose cp agents.xlsx app:/tmp/agents.xlsx
docker compose exec app php artisan agents:importer /tmp/agents.xlsx
docker compose exec app php artisan acces:generer-comptes
```

## Mettre à jour

```bash
cd ~/femme-modele
git pull
docker compose up -d --build
```

Les conteneurs sont recréés avec le nouveau code ; les migrations passent au démarrage de `app`.
**Pas de mise à jour pendant un scrutin ouvert** : le redémarrage coupe le site quelques secondes.

## Avant chaque scrutin

1. Vérifier que les e-mails partent : se connecter avec son propre compte.
2. Vérifier les conteneurs : `docker compose ps` (tous `running`), et `docker compose logs --tail=20 scheduler`.
3. Passer les sauvegardes à une par heure pendant le vote : `SAUVEGARDE_INTERVALLE=3600` dans le `.env`, puis
   `docker compose up -d sauvegarde`. Revenir à `21600` après la clôture.
4. Facultatif, la veille : la simulation de charge (voir plus bas).

## Commandes utiles

| Besoin | Commande |
|---|---|
| État des conteneurs | `docker compose ps` |
| Journaux | `docker compose logs -f app worker worker-codes scheduler` |
| Une commande artisan | `docker compose exec app php artisan ...` |
| Tâches en échec | écran « Tâches en échec » (administrateur), ou `docker compose exec app php artisan queue:failed` |
| Console PostgreSQL | `docker compose exec db psql -U femme_modele -d femme_modele` |
| Sauvegarde immédiate | `docker compose exec -T db pg_dump -U femme_modele -Fc femme_modele > sauvegardes/manuelle.dump` |
| Arrêter | `docker compose down` (les volumes, donc les données, sont gardés) |

`docker compose down -v` supprime aussi les volumes, donc **toute la base et tous les bulletins** : à ne jamais lancer sur le serveur.

## Sauvegardes et restauration

Le conteneur `sauvegarde` écrit `sauvegardes/femme-modele-AAAAMMJJ-HHMM.dump` (format custom de `pg_dump`).
Copier régulièrement ce dossier **hors du serveur** : une sauvegarde sur le même disque ne protège pas d'une panne de disque.
Ces fichiers contiennent les agents, leurs adresses et les bulletins : à conserver en lieu sûr.

Restaurer une sauvegarde (efface la base actuelle) :

```bash
docker compose stop app worker worker-codes scheduler
docker compose exec -T db pg_restore -U femme_modele -d femme_modele --clean --if-exists < sauvegardes/femme-modele-AAAAMMJJ-HHMM.dump
docker compose start app worker worker-codes scheduler
```

La chaîne des empreintes d'une base restaurée ne se vérifie qu'avec la même `APP_KEY`.

## Performances

Les réglages visent un serveur de 4 Go de RAM et au plus 1 000 électeurs. La simulation de charge a mesuré
environ 60 votes par seconde dans un même scrutin : les 1 000 votes passeraient en moins de 20 secondes,
même s'ils arrivaient tous ensemble.

### PHP : OPcache et cache des chemins (`docker/php.ini`)

| Réglage | Valeur | Rôle |
|---|---|---|
| `opcache.validate_timestamps` | `0` | Le code compilé reste en mémoire sans revérifier les fichiers : il ne change qu'avec une nouvelle image |
| `opcache.memory_consumption` | `128` (Mo) | Mémoire du code compilé |
| `opcache.interned_strings_buffer` | `16` (Mo) | Chaînes partagées (noms de classes, de méthodes) |
| `realpath_cache_size` / `realpath_cache_ttl` | `4096K` / `600` (s) | Chemins des fichiers gardés en mémoire |
| `memory_limit` | `256M` | Suffit pour les procès-verbaux PDF ; aucun fichier n'est téléversé par les écrans |

En plus, au démarrage de chaque conteneur PHP, `php artisan optimize` met en cache la configuration, les routes,
les vues et les événements (`docker/entrypoint.sh`).

### PHP-FPM : requêtes simultanées (`docker/php-fpm.conf`)

L'image PHP ne traite que 5 requêtes à la fois par défaut. Le nombre se règle dans le `.env`, sans reconstruire :

```
PHP_FPM_MAX_CHILDREN=30
```

Compter environ 50 Mo par processus. Après un changement : `docker compose up -d app`.

### PostgreSQL (`compose.yaml`, service `db`)

Réglables dans le `.env`, sans toucher `compose.yaml` :

| Variable | Défaut | Règle |
|---|---|---|
| `PG_SHARED_BUFFERS` | `1GB` | Un quart de la RAM |
| `PG_EFFECTIVE_CACHE_SIZE` | `2GB` | La moitié de la RAM |
| `PG_WORK_MEM` | `8MB` | Mémoire par tri ou regroupement |
| `PG_MAINTENANCE_WORK_MEM` | `256MB` | Index, `VACUUM`, migrations |

`synchronous_commit` reste activé : un vote annoncé comme enregistré à l'électeur doit déjà être écrit sur disque.
Après modification : `docker compose up -d db` (quelques secondes d'interruption, hors scrutin ouvert).

### Cache

Seul le cache fichier est utilisé (`CACHE_STORE=file`), dans le volume `stockage` partagé par `app`, les workers et
le planificateur : une édition enregistrée depuis un écran efface bien le cache lu par les autres conteneurs.

### Simulation de charge

Sur une base dédiée, jamais sur la base réelle (les bulletins créés ne peuvent pas être supprimés) :

```bash
docker compose exec db createdb -U femme_modele femme_modele_charge
docker compose run --rm --no-deps -e APP_ENV=simulation -e DB_DATABASE=femme_modele_charge app php artisan migrate --seed --force
docker compose run --rm --no-deps -e APP_ENV=simulation -e DB_DATABASE=femme_modele_charge app php artisan vote:simuler-charge --electeurs=1000 --paralleles=8 --force
docker compose exec db dropdb -U femme_modele femme_modele_charge
```

## Serveur sans accès à Internet

Let's Encrypt doit joindre le serveur depuis Internet. Si le serveur n'est accessible que depuis le réseau
de la DGTCP, remplacer dans `docker/Caddyfile` la ligne `reverse_proxy` par :

```
	tls internal
	reverse_proxy web:80
```

Caddy crée alors son propre certificat : les navigateurs l'acceptent après installation de l'autorité de Caddy
sur les postes, ou utiliser un certificat fourni par le service informatique (`tls /chemin/cert.pem /chemin/cle.pem`).

## Accéder à la base depuis son poste

La base n'écoute que sur `127.0.0.1:5433` du serveur : on y passe par un tunnel SSH.

```bash
ssh -N -L 5433:127.0.0.1:5433 deploy@IP_DU_SERVEUR
```

Puis se connecter à `localhost:5433`, base `femme_modele`, utilisateur `femme_modele`, mot de passe `DB_PASSWORD` du `.env`.

## Sécurité

- **`APP_KEY` ne change jamais** une fois la première édition lancée : elle signe la chaîne des empreintes des
  bulletins, les codes de connexion, les liens de vérification et les tâches chiffrées. Une autre clé rend les
  urnes invérifiables. La conserver hors du serveur, avec les sauvegardes.
- Docker contourne le pare-feu UFW pour les ports publiés : seul `caddy` publie des ports publics, et `db`
  n'est publié que sur `127.0.0.1`. Ne jamais retirer ce `127.0.0.1`.
- Laravel fait confiance aux en-têtes `X-Forwarded` (`bootstrap/app.php`), posés par Caddy : `app` et `web` ne
  doivent jamais publier de port, sinon un visiteur pourrait se faire passer pour Caddy.
- Les limites de tentatives (code de connexion, vote) comptent par matricule ou par compte, pas par adresse IP :
  les agents qui sortent par la même adresse du réseau de la DGTCP ne se bloquent pas entre eux.
- Le `.env` du serveur n'est jamais versionné ni copié dans l'image (voir `.dockerignore`).

## Essai en local

Avec un fichier d'environnement à part (le `.env` de développement n'est pas fait pour Docker) :

```bash
cp .env.production.example .env.docker   # puis APP_KEY, DB_PASSWORD, APP_URL=http://localhost:8089, SITE_DOMAIN vide,
                                         # HTTP_PORT=8089, HTTPS_PORT=8449, DB_FORWARD_PORT=5439, SESSION_SECURE_COOKIE=false, MAIL_MAILER=log
ENV_FILE=.env.docker docker compose --env-file .env.docker -p femme-modele-essai up -d --build
```
