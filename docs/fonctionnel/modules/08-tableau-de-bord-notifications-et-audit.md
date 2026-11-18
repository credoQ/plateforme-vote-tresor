# Tableau de bord, notifications et audit

**Module de tableau de bord, notifications et audit**

## Objectif

Ce module assure le pilotage, l’information des agents et la traçabilité.

## Fonctionnalités

- [ ] Le suivi en temps réel du taux de participation par scrutin, par structure et par département (sans affichage des voix avant la clôture).
- [ ] L’affichage d’annonces sur la plateforme (ouverture/clôture des scrutins, publication des résultats) et, le cas échéant, l’envoi de notifications lorsque les contacts sont disponibles.
- [ ] Le journal d’audit de toutes les opérations sensibles, consultable par les observateurs.
- [ ] Les exports (listes, résultats, procès-verbaux) aux formats PDF et Excel.

## Acteurs

Comité d'organisation et point focal (suivi) ; observateur (lecture des résultats et du journal d'audit).

## Entités du MCD

JOURNAL_AUDIT, INSCRIRE (participation)

## Maquette

- `resources/views/maquettes/tableau-de-bord.blade.php`, consultable à l'adresse `/maquettes/tableau-de-bord`

## Règles de gestion

Règles R56 à R57, extraites de [regles-de-gestion.md](../regles-de-gestion.md) :

### Sécurité et traçabilité

- **R56** · Toutes les opérations sensibles (connexion, import, modification de liste, désactivation ou réactivation de candidate, ouverture/clôture, validation et publication des résultats) sont enregistrées dans un journal d’audit non modifiable (utilisateur, date-heure, action, entité, adresse IP).
- **R57** · Aucun utilisateur, y compris l’administrateur, ne peut consulter le choix d’un électeur ni modifier un bulletin.
