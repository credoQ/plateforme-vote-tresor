# Éditions et phases

**Module de gestion des éditions et des phases**

## Objectif

Ce module permet au comité d’organisation de paramétrer et piloter chaque édition du concours.

## Fonctionnalités

- [ ] La création d’une édition (année, libellé, calendrier, date de gel des listes électorales).
- [ ] Le paramétrage des règles de l’édition (vote blanc autorisé, quorum, nombre de qualifiées par scrutin).
- [ ] La création automatique des trois phases et la planification de leurs dates.
- [ ] L’ouverture et la clôture des phases, dans l’ordre, après validation des résultats de la phase précédente.

## Acteurs

Comité d'organisation.

## Entités du MCD

EDITION, PHASE, SCRUTIN

## Maquette

- `resources/views/maquettes/tableau-de-bord.blade.php`, consultable à l'adresse `/maquettes/tableau-de-bord`

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
