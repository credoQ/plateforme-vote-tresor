# Tableau de bord, notifications et audit

**Module de tableau de bord, notifications et audit**

## Objectif

Ce module assure le pilotage, l’information des agents et la traçabilité.

## Fonctionnalités

- [ ] Le suivi en temps réel du taux de participation par scrutin, par structure et par département (sans affichage des voix avant la clôture).
- [ ] L’affichage d’annonces sur la plateforme (ouverture/clôture des scrutins, publication des résultats) et l’envoi de notifications par e-mail aux agents dont l’adresse est connue (pas de SMS).
- [ ] Le journal d’audit de toutes les opérations sensibles, consultable par les observateurs.
- [x] Les exports (listes, résultats, procès-verbaux) aux formats PDF et Excel.

## Avancement

- [x] Table `journal_audit` en ajout seul : triggers qui refusent toute modification, suppression ou vidage (R56)
- [x] Action `Journaliser` et enum `ActionAuditee`, appelées dans la transaction de chaque opération sensible : connexions (réussies, échouées), déconnexions, comptes, réinitialisations, import des agents, lancement d'édition, ouverture et clôture des phases, génération des scrutins, arrêt des listes, désactivation et réactivation des candidates, ouverture, annulation, fermeture et prolongation des scrutins, dépouillement, départage, validation et publication (R56). Le vote n'est jamais journalisé (R43, R57)
- [x] Écran du journal d'audit, filtrable, pour l'observateur, le comité et l'administrateur
- [x] Tableau de bord de participation par scrutin, département et structure, sans aucune voix (R46) ; le point focal ne voit que sa structure (R4)
- [x] Annonces sur l'accueil (scrutins ouverts, résultats publiés) et e-mails aux inscrits dont l'adresse est connue, à l'ouverture et à la publication (job `EnvoyerAnnonceScrutin`)
- [x] Exports CSV de la liste électorale (périmètre du point focal) et des résultats publiés
- [x] Tests des règles R4, R46, R56 et R57
- [x] Exports Excel (.xlsx, OpenSpout) et PDF (dompdf) de la liste électorale et des résultats publiés, en plus du CSV

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
