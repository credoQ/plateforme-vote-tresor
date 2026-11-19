# Éditions et phases

**Module de gestion des éditions et des phases**

## Objectif

Ce module permet au comité d’organisation de paramétrer et piloter chaque édition du concours.

## Fonctionnalités

- [x] La création d’une édition (année, libellé, calendrier, date de gel des listes électorales).
- [x] Le paramétrage des règles de l’édition (vote blanc autorisé, quorum, nombre de qualifiées par scrutin).
- [x] La création automatique des trois phases et la planification de leurs dates.
- [x] L’ouverture et la clôture des phases, dans l’ordre, après validation des résultats de la phase précédente.

## Avancement

- [x] Migrations `editions` (une édition par année, une seule en cours), `phases` (trois par édition) et `scrutins` (socle commun avec le module 05)
- [x] Enums `StatutEdition`, `StatutPhase` et `StatutScrutin`
- [x] Modèles `Edition`, `Phase` et `Scrutin`
- [x] Action `EnregistrerEdition` : création avec les trois phases (R16, R17), vote blanc et quorum en paramètres de l'édition
- [x] Action `ParametrerPhase` : calendrier ordonné, après le gel et dans l'édition, nombre de qualifiées par scrutin (R52)
- [x] Actions `LancerEdition`, `OuvrirPhase` et `CloturerPhase` : enchaînement des phases selon R16 et R18 ; la clôture de la finale clôture l'édition
- [x] Tests des règles R16 à R18
- [x] Écrans du comité : liste des éditions, création et paramétrage, planification des phases, lancement, ouverture et clôture des phases avec confirmation
- [x] Journalisation des ouvertures et clôtures (R56, module 08)

## Acteurs

Comité d'organisation.

## Entités du MCD

EDITION, PHASE, SCRUTIN

## Maquette

- `resources/views/maquettes/tableau-de-bord.blade.php`, consultable à l'adresse `/maquettes/tableau-de-bord`

## Écrans

- `/editions` : liste, création et paramétrage des éditions, planification, lancement, ouverture et clôture des phases (comité)

## Règles de gestion

Règles R16 à R23, extraites de [regles-de-gestion.md](../regles-de-gestion.md) :

### Gestion des éditions, des phases et des scrutins

- **R16** · Une édition est identifiée par un id et possède une année, un libellé, des dates de début et de fin, une date de gel des listes et un statut ; une seule édition peut être en cours.
- **R17** · Une édition comporte exactement trois phases ordonnées : Phase 1 (primaires), Phase 2 (demi-finales), Phase 3 (finale).
- **R18** · Une phase ne peut être ouverte que si les résultats de tous les scrutins de la phase précédente ont été validés et publiés.
- **R19** · Une phase comporte un ou plusieurs scrutins ; un scrutin appartient à une seule phase et possède une date-heure d’ouverture et de fermeture.
- **R20** · La phase 1 comporte 12 scrutins départementaux et un scrutin par structure électorale du pôle structures comptant au moins une agent de sexe féminin en service à la date de gel.
- **R21** · La phase 2 comporte deux scrutins : la demi-finale départementale et la demi-finale des structures.
- **R22** · Un scrutin relève d’au plus un pôle électoral : les scrutins des phases 1 et 2 relèvent d’un pôle, la finale n’en relève d’aucun puisqu’elle réunit les deux pôles. Le corps électoral d’une demi-finale est constitué des agents des structures dont le type est classé dans le pôle du scrutin.
- **R23** · La phase 3 comporte un scrutin unique : la finale.
