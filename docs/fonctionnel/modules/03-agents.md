# Agents et affectations

**Module de gestion des agents**

## Objectif

Ce module permet l’enregistrement des agents et la tenue de leurs affectations.

## Fonctionnalités

- [ ] L’import des agents depuis le système d’information des ressources humaines ou par fichier (Excel/CSV), et la saisie manuelle.
- [ ] L’enregistrement des informations utiles de l’agent (matricule, NPI, nom, prénoms, sexe) ; la date de prise de service, le téléphone, l’e-mail, la fonction et le statut peuvent être importés à titre informatif mais ne sont pas gérés.
- [ ] La gestion des affectations (structure, date de début, date de fin) et de leur historique.
- [ ] Le contrôle des doublons et des données incomplètes (sexe, affectation), le sexe déterminant la qualité de candidate.

## Avancement

- [x] Migrations `agents` (matricule et NPI uniques) et `affectations` (une seule affectation en cours par agent, période cohérente)
- [x] Enum `Sexe` (F, M), avec lecture des écritures usuelles des fichiers d'import
- [x] Modèles `Agent` et `Affectation`, avec les portées `enServiceLe()`, `femmes()` et `enCoursLe()`
- [x] Actions `EnregistrerAgent` (R11), `AffecterAgent` (R12 : mutation et historique) et `TerminerAffectation` (R13)
- [x] Action `ImporterAgents` et commande `php artisan agents:importer fichier.csv` : création ou mise à jour par matricule, rejet motivé des doublons et des lignes incomplètes
- [x] Tests des règles R11 à R13
- [ ] Import des fichiers Excel (.xlsx) : demande l'ajout d'une dépendance, à valider ; en attendant, enregistrer le fichier en CSV
- [ ] Écrans de gestion des agents et de contrôle par le point focal (avec le module 01)
- [ ] Format exact de l'export du SI RH, à obtenir

## Acteurs

Administrateur (import et mise à jour) ; point focal (contrôle des agents de sa structure).

## Entités du MCD

AGENT, AFFECTER (affectations)

## Règles de gestion

Règles R11 à R15, extraites de [regles-de-gestion.md](../regles-de-gestion.md) :

### Gestion des agents et des affectations

- **R11** · Un agent est identifié par son matricule, unique ; il possède un NPI, un nom, des prénoms, un sexe et une date de naissance. La date de prise de service, le téléphone, l’e-mail, la fonction et le statut peuvent figurer dans la fiche à titre informatif, mais ne sont pas gérés par la plateforme : aucune règle ne s’appuie sur eux.
- **R12** · Un agent est affecté à une seule structure à une date donnée ; l’historique des affectations est conservé.
- **R13** · Un agent est considéré en service lorsqu’il figure dans la liste des agents avec une affectation en cours dans une structure de la DGTCP ; la tenue à jour de cette liste relève de l’import des agents et des affectations.
- **R14** · Est électeur tout agent en service à la date de gel des listes électorales (les catégories exactes d’agents admis à voter sont à confirmer par le métier).
- **R15** · Les mutations intervenues après la date de gel ne modifient pas les listes électorales de l’édition en cours.
