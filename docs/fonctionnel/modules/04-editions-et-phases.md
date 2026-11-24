# Éditions et phases

**Module de gestion des éditions et des phases**

## Objectif

Ce module permet au comité d’organisation de paramétrer et piloter chaque édition du concours.

## Fonctionnalités

- [x] La création d’une édition (année, libellé, calendrier) ; la date de gel des listes électorales est fixée au premier arrêt des listes.
- [x] Le paramétrage des règles de l’édition (vote blanc autorisé, quorum).
- [x] Le paramétrage des phases à la création de l’édition (une à trois, avec leurs collèges : découpage, origine des candidates, nombre d’élues) et la planification de leurs dates.
- [x] L’ouverture et la clôture des phases, dans l’ordre, après validation des résultats de la phase précédente.

## Avancement

- [x] Migrations `editions` (une édition par année, une seule en cours), `phases` (une à trois par édition) et `scrutins` (socle commun avec le module 05)
- [x] Enums `StatutEdition`, `StatutPhase` et `StatutScrutin`
- [x] Modèles `Edition`, `Phase` et `Scrutin`
- [x] Action `EnregistrerEdition` : création avec ses phases (R16, R17), vote blanc et quorum en paramètres de l'édition
- [x] Action `ParametrerPhase` : calendrier ordonné et dans l'édition
- [x] Actions `LancerEdition`, `OuvrirPhase` et `CloturerPhase` : enchaînement des phases selon R16 et R18 ; la clôture de la dernière phase clôture l'édition
- [x] Migration `colleges` et action `OrganiserPhases` : une à trois phases, collèges par pôle ou pour l'ensemble de la DGTCP, découpage, origine des candidates (d'office, nomination, qualification) et nombre d'élues par scrutin (R17, R20 à R23, R28, R52) ; paramétrage figé dès que des scrutins sont générés ; éditions existantes reprises avec l'organisation par défaut
- [x] Tests des règles R16 à R23, R52
- [x] Écrans du comité : liste des éditions, création et paramétrage, planification des phases, lancement, ouverture et clôture des phases avec confirmation
- [x] Journalisation des ouvertures et clôtures (R56, module 08)

## Acteurs

Comité d'organisation.

## Entités du MCD

EDITION, PHASE, COLLEGE, SCRUTIN

## Maquette

- `resources/views/maquettes/tableau-de-bord.blade.php`, consultable à l'adresse `/maquettes/tableau-de-bord`

## Écrans

- `/editions` : liste, création et paramétrage des éditions, planification, lancement, ouverture et clôture des phases (comité)

## Règles de gestion

Règles R16 à R23, extraites de [regles-de-gestion.md](../regles-de-gestion.md) :

### Gestion des éditions, des phases et des scrutins

- **R16** · Une édition est identifiée par un id et possède une année, un libellé, des dates de début et de fin, une date de gel des listes (fixée au premier arrêt des listes, R14) et un statut ; une seule édition peut être en cours.
- **R17** · Une édition comporte une à trois phases ordonnées, paramétrées par le comité à la création de l’édition : le libellé de chaque phase et ses collèges (R20). Ce paramétrage se modifie tant que l’édition est en préparation et qu’aucun scrutin n’est généré ; il est ensuite figé. L’organisation proposée par défaut est décrite en R23.
- **R18** · Une phase ne peut être ouverte que si les résultats de tous les scrutins de la phase précédente ont été validés et publiés.
- **R19** · Une phase comporte un ou plusieurs scrutins ; un scrutin appartient à une seule phase et possède une date-heure d’ouverture et de fermeture.
- **R20** · Chaque phase déclare un ou plusieurs collèges : le pôle départemental, le pôle structures, ou l’ensemble de la DGTCP, qui est alors le seul collège de la phase. Pour chaque collège, le comité fixe le découpage (un scrutin par circonscription, c’est-à-dire par département ou par structure électorale, ou un seul scrutin pour tout le collège), l’origine des candidates (R28) et le nombre d’élues par scrutin (R52).
- **R21** · Une structure dont le pôle n’a pas de collège dans une phase n’y a pas de scrutin : ses agents ne votent pas dans cette phase.
- **R22** · Un scrutin relève d’au plus un pôle électoral, celui de son collège ; un scrutin de l’ensemble de la DGTCP ne relève d’aucun pôle. Le corps électoral d’un scrutin d’un seul tenant pour un pôle est constitué des agents des structures de ce pôle.
- **R23** · Organisation proposée par défaut : phase 1 (primaires), un scrutin par département et par structure électorale, candidates d’office ; phase 2 (demi-finales), un scrutin par pôle, lauréates qualifiées ; phase 3 (finale), un scrutin pour l’ensemble de la DGTCP, lauréates qualifiées ; une élue par scrutin.
