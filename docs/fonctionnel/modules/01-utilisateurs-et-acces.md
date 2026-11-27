# Utilisateurs et accès

**Module de gestion des utilisateurs et des accès**

## Objectif

Ce module assure la gestion des comptes, des profils et des niveaux d’accès à la plateforme.

## Fonctionnalités

- [x] La création et l’administration des comptes utilisateurs (administrateurs, membres du comité d’organisation, points focaux, agents, observateurs).
- [x] La gestion des rôles et des habilitations, avec restriction du périmètre du point focal à sa structure.
- [x] L’authentification par matricule et code à usage unique (OTP) envoyé par e-mail, sans mot de passe.

## Avancement

- [x] Comptes : identifiant (matricule pour un agent), statut actif, dernière connexion, lien unique vers un agent (R1, R3), structure du point focal (R4), adresse e-mail obligatoire si la fiche agent n'en a pas
- [x] Rôles en table de référence et attribution multiple (R2), `RolesSeeder`
- [x] Connexion sans mot de passe (R42) : matricule, puis code à 6 chiffres envoyé par e-mail (`DemanderCodeConnexion`, `VerifierCodeConnexion`), valable 10 minutes, à usage unique, cinq essais au plus, trois envois par matricule toutes les dix minutes ; l'écran ne révèle pas si le matricule existe ; code stocké sous forme d'empreinte
- [x] Journal : code envoyé, échec, connexion, déconnexion (R56), sans jamais le code
- [x] Droits : gates `administrer`, `piloter`, `controler-listes`, `observer`, `voter` ; policies `CandidatePolicy` (R32), `AgentPolicy` et `InscriptionPolicy` limitées au périmètre du point focal (R4)
- [x] Écrans : connexion (matricule), code de connexion, accueil, administration des comptes
- [x] Commandes `acces:creer-administrateur login nom email` et `acces:generer-comptes` (comptes électeurs des agents en service, liste des agents sans adresse e-mail)
- [x] Mode de connexion « code imprimé » (`CONNEXION_MODE=code_imprime`, exception à R42) : un code personnel par compte, chiffré, valable pour l'édition ; écran « Codes d'accès » (comité, administrateur) pour générer les codes manquants, régénérer un code perdu et imprimer les coupons (électeurs par structure, comptes de gestion à part, coupon individuel, sélection par cases à cocher et filtre par structure) ou les télécharger en archive ZIP préparée en file d'attente (un dossier par structure, un PDF par personne), tracé au journal ; aucun e-mail envoyé dans ce mode
- [x] Tests des règles R1 à R4, R32 et R42

## Acteurs

Administrateur (gestion des comptes et des rôles) ; tous les profils (connexion).

## Entités du MCD

UTILISATEUR, ROLE, AGENT (lien DISPOSER)

## Maquette

- `resources/views/maquettes/connexion.blade.php`, consultable à l'adresse `/maquettes/connexion`

## Écrans

- `/connexion` puis `/connexion/code` : connexion par matricule et code reçu par e-mail
- `/espace` : accueil de l'utilisateur connecté
- `/utilisateurs` : administration des comptes (administrateur)

## Règles de gestion

Règles R1 à R4, R56 à R57, extraites de [regles-de-gestion.md](../regles-de-gestion.md) :

### Gestion des utilisateurs et des rôles

- **R1** · Un utilisateur est identifié par un id et possède un login (le matricule pour un agent), une adresse e-mail (la sienne ou celle de sa fiche agent) qui reçoit ses codes de connexion, un statut (actif/inactif) et une date de dernière connexion. Il n’a pas de mot de passe.
- **R2** · Un utilisateur possède un ou plusieurs rôles parmi : Administrateur, Comité d’organisation, Point focal, Électeur, Observateur.
- **R3** · Un agent dispose d’au plus un compte utilisateur ; un compte peut ne pas être lié à un agent (administrateur technique, observateur externe).
- **R4** · Le point focal n’intervient que sur les agents et la liste électorale de la structure à laquelle il est rattaché.

### Sécurité et traçabilité

- **R56** · Toutes les opérations sensibles (demande de code et connexion, gestion des comptes, des structures et des agents, import des agents, ajout et retrait dans l’historique des femmes modèles, génération, arrêt et réouverture des listes, nomination et retrait de nomination, désactivation ou réactivation de candidate, ouverture et clôture des phases et des scrutins, dépouillement, départage, validation et publication des résultats, relance et suppression des tâches en échec) sont enregistrées dans un journal d’audit non modifiable (utilisateur, date-heure, action, entité, adresse IP). Le vote lui-même n’y figure jamais.
- **R57** · Aucun utilisateur, y compris l’administrateur, ne peut consulter le choix d’un électeur ni modifier un bulletin.
