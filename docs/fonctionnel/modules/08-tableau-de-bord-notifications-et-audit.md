# Tableau de bord, notifications et audit

**Module de tableau de bord, notifications et audit**

## Objectif

Ce module assure le pilotage, l’information des agents et la traçabilité.

## Fonctionnalités

- [x] Le suivi en temps réel du taux de participation par scrutin, par structure et par département (sans affichage des voix avant la clôture).
- [x] L’affichage d’annonces sur la plateforme (ouverture/clôture des scrutins, publication des résultats) et l’envoi de notifications par e-mail aux agents dont l’adresse est connue (pas de SMS).
- [x] Le journal d’audit de toutes les opérations sensibles, consultable par les observateurs.
- [x] Les exports (listes, résultats, procès-verbaux) aux formats PDF et Excel.

## Avancement

- [x] Table `journal_audit` en ajout seul : triggers qui refusent toute modification, suppression ou vidage (R56)
- [x] Action `Journaliser` et enum `ActionAuditee`, appelées dans la transaction de chaque opération sensible : demandes de code et connexions (réussies, échouées), déconnexions, comptes, fiches et affectations des agents, import des agents, lancement d'édition, ouverture et clôture des phases, génération des scrutins, arrêt des listes, désactivation et réactivation des candidates, ouverture, annulation, fermeture et prolongation des scrutins, dépouillement, départage, validation et publication (R56). Le vote n'est jamais journalisé (R43, R57)
- [x] Écran du journal d'audit, filtrable, pour l'observateur, le comité et l'administrateur
- [x] Tableau de bord de participation par scrutin, département et structure, sans aucune voix (R46) ; le point focal ne voit que sa structure (R4)
- [x] Annonces sur l'accueil (scrutins ouverts, résultats publiés) et e-mails aux inscrits dont l'adresse est connue, à l'ouverture et à la publication (job `EnvoyerAnnonceScrutin`)
- [x] Écran « Tâches en échec » pour l'administrateur : erreur et trace, relance d'une tâche ou de toutes, suppression, chaque opération journalisée ; relance de la génération d'un procès-verbal depuis la fiche du scrutin
- [x] En-tête et bas de page communs des PDF (modèle de quittance de la DGTCP) et QR code de vérification en ligne : lien signé vers une page publique qui confirme l'authenticité du document et signale une liste qui a changé depuis son édition, sans donnée personnelle ni résultat avant publication
- [x] Tests des règles R4, R46, R56 et R57
- [x] Exports CSV, Excel (.xlsx, OpenSpout) et PDF (dompdf) de la liste électorale (périmètre du point focal) et des résultats publiés
- [x] Envoi asynchrone de tous les e-mails : notifications en file d’attente (`urgent` pour les codes de connexion, chiffrés ; `mails` pour les annonces), avec tentatives et reprise

## Acteurs

Comité d'organisation et point focal (suivi) ; observateur (lecture des résultats et du journal d'audit).

## Entités du MCD

JOURNAL_AUDIT, INSCRIRE (participation)

## Maquette

- `resources/views/maquettes/tableau-de-bord.blade.php`, consultable à l'adresse `/maquettes/tableau-de-bord`

## Écrans

- `/tableau-de-bord` : participation (comité, observateur, point focal pour sa structure)
- `/journal` : journal d'audit (observateur, comité, administrateur)
- `/taches-echouees` : tâches en échec de la file d'attente (administrateur)

## Règles de gestion

Règles R56 à R57, extraites de [regles-de-gestion.md](../regles-de-gestion.md) :

### Sécurité et traçabilité

- **R56** · Toutes les opérations sensibles (demande de code et connexion, gestion des comptes, des structures et des agents, import des agents, ajout et retrait dans l’historique des femmes modèles, génération, arrêt et réouverture des listes, nomination et retrait de nomination, désactivation ou réactivation de candidate, ouverture et clôture des phases et des scrutins, dépouillement, départage, validation et publication des résultats, relance et suppression des tâches en échec) sont enregistrées dans un journal d’audit non modifiable (utilisateur, date-heure, action, entité, adresse IP). Le vote lui-même n’y figure jamais.
- **R57** · Aucun utilisateur, y compris l’administrateur, ne peut consulter le choix d’un électeur ni modifier un bulletin.
