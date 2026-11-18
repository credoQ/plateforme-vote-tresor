---
paths:
  - 'app/**/Vote/**'
  - 'app/Models/Bulletin.php'
  - 'app/Models/Inscription.php'
  - 'database/migrations/*bulletins*'
  - 'database/migrations/*inscriptions*'
---

# Secret et intégrité du vote

## Le bulletin ne permet jamais d'identifier l'électeur

- Aucune relation, aucune colonne, aucun log et aucun événement ne relie un `Bulletin` à un `Agent` ou à un `Utilisateur`.

## Enregistrement d'un vote

- L'émargement (`inscriptions.a_vote`, `date_heure_vote`) et la création du bulletin se font dans une seule transaction de base de données.
- Verrouillez l'inscription (`lockForUpdate`) pour empêcher un double vote lors des pics de connexion.

## Horodatage du bulletin

- L'horodatage du bulletin est volontairement imprécis (tronqué à l'heure), pour empêcher tout rapprochement avec l'heure d'émargement.

## Empreinte chaînée

- Chaque bulletin porte une empreinte (hachage du bulletin précédent et du bulletin courant).
- Un bulletin n'est jamais modifié ni supprimé.

## Pendant le scrutin

- Seul le taux de participation est exposé. Le nombre de voix par candidate n'est calculé qu'après la clôture.
