#!/bin/sh
# Démarrage des conteneurs app, worker, worker-codes et scheduler.
set -e

# Le volume de stockage peut être vide au premier lancement : on recrée l'arborescence attendue.
# storage/fonts : polices et métriques de dompdf (procès-verbaux, accusés de vote).
mkdir -p storage/app/private storage/app/public storage/logs storage/fonts \
    storage/framework/cache/data storage/framework/sessions storage/framework/views

# Seul le conteneur app migre (RUN_MIGRATIONS=1), pour ne pas lancer deux migrations en même temps.
if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    php artisan migrate --force
fi

# Mise en cache de la configuration, des routes, des vues et des événements : à refaire à chaque démarrage,
# puisque la configuration vient des variables d'environnement.
php artisan optimize

exec "$@"
