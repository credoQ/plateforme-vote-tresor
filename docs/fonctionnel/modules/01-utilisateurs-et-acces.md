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
- [x] Tests des règles R1 à R4, R32 et R42

## Acteurs

Administrateur (gestion des comptes et des rôles) ; tous les profils (connexion).

## Entités du MCD

UTILISATEUR, ROLE, AGENT (lien DISPOSER)

## Maquette

- `resources/views/maquettes/connexion.blade.php`, consultable à l'adresse `/maquettes/connexion`

## Règles de gestion

Règles R1 à R4, R56 à R57, extraites de [regles-de-gestion.md](../regles-de-gestion.md) :

### Gestion des utilisateurs et des rôles

- **R1** · Un utilisateur est identifié par un id et possède un login, un mot de passe chiffré, un statut (actif/inactif) et une date de dernière connexion.
- **R2** · Un utilisateur possède un ou plusieurs rôles parmi : Administrateur, Comité d’organisation, Point focal, Électeur, Observateur.
- **R3** · Un agent dispose d’au plus un compte utilisateur ; un compte peut ne pas être lié à un agent (administrateur technique, observateur externe).
- **R4** · Le point focal n’intervient que sur les agents et la liste électorale de la structure à laquelle il est rattaché.

### Sécurité et traçabilité

- **R56** · Toutes les opérations sensibles (connexion, import, modification de liste, désactivation ou réactivation de candidate, ouverture/clôture, validation et publication des résultats) sont enregistrées dans un journal d’audit non modifiable (utilisateur, date-heure, action, entité, adresse IP).
- **R57** · Aucun utilisateur, y compris l’administrateur, ne peut consulter le choix d’un électeur ni modifier un bulletin.
