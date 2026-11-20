#!/bin/sh
# Conteneur sauvegarde : copie complète de la base au format custom de pg_dump (restaurable avec pg_restore),
# puis suppression des copies plus anciennes que SAUVEGARDE_JOURS jours.
set -e

while true; do
    fichier="/sauvegardes/femme-modele-$(date +%Y%m%d-%H%M).dump"
    if pg_dump --format=custom --file="$fichier.partiel"; then
        mv "$fichier.partiel" "$fichier"
        echo "Sauvegarde écrite : $fichier"
    else
        rm -f "$fichier.partiel"
        echo "Échec de la sauvegarde" >&2
    fi

    find /sauvegardes -name 'femme-modele-*.dump' -mtime "+${SAUVEGARDE_JOURS:-30}" -delete
    sleep "${SAUVEGARDE_INTERVALLE:-21600}"
done
