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
- `scrutins.pole_electoral_id` est nullable : un scrutin de l'ensemble de la DGTCP n'a pas de pôle.

## Phases et collèges

- `colleges` relie une phase à un pôle électoral (nullable : l'ensemble de la DGTCP) et porte `decoupage`, `origine_candidates` et `nb_elues`.
- Chaque scrutin porte `college_id` (obligatoire) : l'origine de ses candidates et son nombre d'élues se lisent sur son collège, jamais sur le numéro de la phase.

## Candidates

- Une ligne `candidates` relie une agent à un scrutin.
- Elle porte `origine` (automatique, nomination ou qualification), pour une nominée la référence de la décision (`reference_nomination`), son auteur (`nommee_par_id`) et sa date (`date_nomination`), `statut` (active ou désactivée), `date_desistement`, `motif_desistement` et le résultat (`nb_voix`, `rang`, `est_laureate`).
- `candidate_source_id` (auto-référence nullable) relie une candidate qualifiée à sa candidate de la phase précédente.

## Inéligibilité

- `femmes_modeles` est l'historique des femmes modèles (une ligne par matricule et par année, `source` plateforme ou import) ; il fonde l'inéligibilité (R59) avec `editions.delai_reeligibilite`. Une femme modèle se repère par son matricule, pas par `agent_id` (nullable pour les éditions antérieures).

## Connexion

- `codes_connexion` garde l'empreinte HMAC du code envoyé par e-mail, jamais le code. Pas de mot de passe sur `users`.

## Traçabilité

- `journal_audit` est en ajout seul : aucune mise à jour, aucune suppression.

## Types de colonnes

- Utilisez `timestampTz` pour les dates et heures et `jsonb` pour les détails d'audit.
- Les tests tournent sur PostgreSQL, pas sur SQLite, pour garder le même comportement.
