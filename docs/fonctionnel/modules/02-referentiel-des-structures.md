# Référentiel des structures

**Module de gestion du référentiel des structures**

## Objectif

Ce module permet de décrire l’organisation de la DGTCP.

## Fonctionnalités

- [x] La gestion des types de structure (Cellule, Direction centrale, TMIE, Trésorerie départementale, ACD, Trésorerie communale) et des pôles électoraux (pôle départemental, pôle structures), chaque type étant classé dans un pôle.
- [x] La gestion des structures et de leur rattachement hiérarchique (service → direction, Trésorerie communale → Trésorerie départementale).
- [x] La gestion des 12 départements géographiques et le rattachement des postes déconcentrés à leur département.

## Avancement

- [x] Migrations `poles_electoraux`, `types_structure`, `departements`, `structures` (rattachement réflexif)
- [x] Enums `CodePoleElectoral` et `CodeTypeStructure` (pôle de chaque type)
- [x] Modèles `PoleElectoral`, `TypeStructure`, `Departement`, `Structure`, avec les portées `electorales()` et `duPole()`
- [x] Action `EnregistrerStructure` : règles R5, R8 et R9, messages d'erreur en français
- [x] `ReferentielSeeder` : 2 pôles, 6 types et 12 départements
- [x] `StructuresCentralesSeeder` : Cabinet, IGS et cellules rattachées, directions techniques, dont la DPCP et la TGE (directions du pôle structures), selon l'AOF de 2022
- [x] `StructuresDeconcentreesSeeder` : réseau comptable des services extérieurs (AOF, section 5) : 12 TD, 77 Trésoreries communales (une par commune, rattachée à la TD de son département), 37 TMIE de l'export SIGFP et l'ACD
- [x] Tests des règles R7 à R10
- [x] Écrans d'administration du référentiel : liste filtrable (type, département, statut), fiche avec structures rattachées et agents en service, création, modification, désactivation et réactivation par l'administrateur, tracées au journal ; consultation par le comité et l'observateur. Les types, pôles et départements sont consultables (onglet « Types et départements ») ; leurs codes sont fixés par R6, R7 et R9
- [x] Contrôles ajoutés : pas de rattachement en boucle (R8), pas de désactivation d'une structure qui a des agents en service ou des structures rattachées actives
- [x] Chargement des TMIE et des Trésoreries communales (`StructuresDeconcentreesSeeder`)

## Acteurs

Administrateur.

## Entités du MCD

POLE_ELECTORAL, TYPE_STRUCTURE, STRUCTURE, DEPARTEMENT

## Écrans

- Structures : liste, fiche, création et modification (`/structures`)
- Types et départements : consultation (`/structures/organisation`)

## Règles de gestion

Règles R5 à R10, extraites de [regles-de-gestion.md](../regles-de-gestion.md) :

### Gestion du référentiel des structures

- **R5** · Une structure est identifiée par un id et possède un code, un sigle, un libellé, un niveau et un statut ; elle est d’un et un seul type de structure.
- **R6** · Un pôle électoral est identifié par un id et possède un code et un libellé ; il en existe deux : le pôle départemental et le pôle structures.
- **R7** · Un type de structure est défini par un code et un libellé, et il est classé dans un et un seul pôle électoral ; un pôle regroupe un ou plusieurs types. Les types sont : Cellule, Direction centrale, TMIE et Agence comptable de la Dette (ACD), classés dans le pôle structures ; Trésorerie départementale (TD) et Trésorerie communale (TC), classées dans le pôle départemental.
- **R8** · Une structure peut être rattachée à une structure mère (un service à sa direction, une Trésorerie communale à sa Trésorerie départementale).
- **R9** · Chaque Trésorerie départementale et chaque Trésorerie communale est située dans l’un des 12 départements.
- **R10** · Chaque structure est autonome et constitue sa propre structure électorale (directions, cellules, TMIE, ACD). Le Cabinet du Directeur général regroupe le Directeur général, son adjoint, l’Assistant du Directeur général et les secrétariats ; les cellules et l’Inspection générale des services sont des structures autonomes rattachées au Cabinet.
