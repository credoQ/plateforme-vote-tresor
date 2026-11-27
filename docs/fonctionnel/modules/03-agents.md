# Agents et affectations

**Module de gestion des agents**

## Objectif

Ce module permet l’enregistrement des agents et la tenue de leurs affectations.

## Fonctionnalités

- [x] L’import des agents depuis le système d’information des ressources humaines ou par fichier (Excel/CSV), et la saisie manuelle.
- [x] L’enregistrement des informations utiles de l’agent (matricule, NPI, nom, prénoms, sexe, adresse e-mail facultative) ; la date de prise de service, le téléphone, la fonction et le statut peuvent être importés à titre informatif mais ne sont pas gérés.
- [x] La gestion des affectations (structure, date de début, date de fin) et de leur historique.
- [x] Le contrôle des doublons et des données incomplètes (sexe, affectation), le sexe déterminant la qualité de candidate.

## Avancement

- [x] Migrations `agents` (matricule et NPI uniques) et `affectations` (une seule affectation en cours par agent, période cohérente)
- [x] Enum `Sexe` (F, M), avec lecture des écritures usuelles des fichiers d'import
- [x] Modèles `Agent` et `Affectation`, avec les portées `enServiceLe()`, `femmes()` et `enCoursLe()`
- [x] Actions `EnregistrerAgent` (R11), `AffecterAgent` (R12 : mutation et historique) et `TerminerAffectation` (R13)
- [x] Action `ImporterAgents` et commande `php artisan agents:importer fichier.xlsx` (ou `.csv`) : création ou mise à jour par matricule, rejet motivé des doublons et des lignes incomplètes
- [x] Tests des règles R11 à R13
- [x] Import des fichiers Excel (.xlsx, première feuille, OpenSpout) en plus du CSV : dates Excel et matricules numériques reconnus, numéros de ligne du classeur dans le rapport
- [x] Écrans de gestion des agents : liste filtrable (structure, sexe, situation), fiche avec historique des affectations, saisie manuelle avec affectation initiale, modification, mutation et fin d'affectation par l'administrateur, tracées au journal ; le point focal contrôle en lecture seule les agents de sa structure et des structures rattachées (R4)
- [x] Compte électeur ouvert à la création de l'agent, par la saisie comme par l'import (R3) : action `OuvrirCompteElecteur`, identifiant = matricule, rôle Électeur ; un compte existant sous ce matricule sans agent lui est rattaché
- [x] Adresse e-mail facultative pour l'agent (valide quand elle est saisie) : sans adresse, il se connecte par code imprimé ; toujours obligatoire à la création d'un compte utilisateur de gestion
- [x] Écran d'import `/agents/importer` (administrateur) : dépôt d'un fichier Excel ou CSV (matricule, nom, prénom, sexe, code de la structure ; e-mail et autres colonnes facultatifs), import en file d'attente (agents, comptes électeurs, affectations), progression, bilan et lignes rejetées avec leur motif
- [ ] Format exact de l'export du SI RH, à obtenir

## Acteurs

Administrateur (import et mise à jour) ; point focal (contrôle des agents de sa structure).

## Entités du MCD

AGENT, AFFECTER (affectations)

## Écrans

- `/agents` : liste, fiche, saisie, import par fichier (`/agents/importer`), mutation et fin d'affectation (administrateur) ; lecture seule pour le comité, l'observateur et le point focal (sa structure)

## Règles de gestion

Règles R11 à R15, extraites de [regles-de-gestion.md](../regles-de-gestion.md) :

### Gestion des agents et des affectations

- **R11** · Un agent est identifié par son matricule, unique ; il possède un NPI, un nom, des prénoms, un sexe, une date de naissance et une adresse e-mail, obligatoire, qui reçoit ses codes de connexion. La date de prise de service, le téléphone, la fonction et le statut peuvent figurer dans la fiche à titre informatif, mais ne sont pas gérés par la plateforme : aucune règle ne s’appuie sur eux.
- **R12** · Un agent est affecté à une seule structure à une date donnée ; l’historique des affectations est conservé.
- **R13** · Un agent est considéré en service lorsqu’il figure dans la liste des agents avec une affectation en cours dans une structure de la DGTCP ; la tenue à jour de cette liste relève de l’import des agents et des affectations.
- **R14** · Est électeur tout agent en service à la date de gel des listes électorales (les catégories exactes d’agents admis à voter sont à confirmer par le métier). La date de gel n’est pas saisie : c’est le jour du premier arrêt des listes de l’édition ; jusque-là, les listes sont provisoires et se calculent à la date du jour.
- **R15** · Les mutations intervenues après la date de gel ne modifient pas les listes électorales de l’édition en cours.
