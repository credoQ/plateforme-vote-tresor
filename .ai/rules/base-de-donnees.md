---
paths:
  - 'database/**'
  - 'app/Models/**'
---

# Base de données (PostgreSQL)

## Nommage

- Noms des tables et des colonnes en français, conformes au MCD du document technique.

## Pôles électoraux

- `poles_electoraux` est une table de référence.
- `types_structure.pole_electoral_id` est obligatoire.
- `scrutins.pole_electoral_id` est nullable : la finale n'a pas de pôle.

## Candidates

- Une ligne `candidates` relie une agent à un scrutin.
- Elle porte `origine` (automatique ou qualification), `statut` (active ou désactivée), `date_desistement`, `motif_desistement` et le résultat (`nb_voix`, `rang`, `est_laureate`).
- `candidate_source_id` (auto-référence nullable) relie une candidate qualifiée à sa candidate de la phase précédente.

## Traçabilité

- `journal_audit` est en ajout seul : aucune mise à jour, aucune suppression.

## Types de colonnes

- Utilisez `timestampTz` pour les dates et heures et `jsonb` pour les détails d'audit.
- Les tests tournent sur PostgreSQL, pas sur SQLite, pour garder le même comportement.
