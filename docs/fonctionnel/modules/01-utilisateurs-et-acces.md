# Utilisateurs et accès

**Module de gestion des utilisateurs et des accès**

## Objectif

Ce module assure la gestion des comptes, des profils et des niveaux d’accès à la plateforme.

## Fonctionnalités

- [ ] La création et l’administration des comptes utilisateurs (administrateurs, membres du comité d’organisation, points focaux, agents, observateurs).
- [ ] La gestion des rôles et des habilitations, avec restriction du périmètre du point focal à sa structure.
- [ ] L’authentification par matricule et mot de passe (mot de passe initial remis par le point focal et changé à la première connexion), avec un code à usage unique (OTP) facultatif, envoyé par e-mail lorsque l’adresse de l’agent est connue.
- [ ] La modification et la réinitialisation des mots de passe.

## Avancement

- [x] Comptes : identifiant (matricule pour un agent), statut actif, dernière connexion, lien unique vers un agent (R1, R3), structure du point focal (R4)
- [x] Rôles en table de référence et attribution multiple (R2), `RolesSeeder`
- [x] Connexion par matricule et mot de passe, limitée à cinq essais par minute, déconnexion (R42)
- [x] Mot de passe provisoire à changer à la première connexion (middleware `ExigerNouveauMotDePasse`), changement du mot de passe
- [x] Droits : gates `administrer`, `piloter`, `controler-listes`, `observer`, `voter` ; policies `CandidatePolicy` (R32), `AgentPolicy` et `InscriptionPolicy` limitées au périmètre du point focal (R4)
- [x] Écrans : connexion, accueil, changement de mot de passe, administration des comptes (liste, création, modification, réinitialisation du mot de passe)
- [x] Commandes `acces:creer-administrateur` et `acces:generer-comptes` (comptes électeurs des agents en service, mots de passe provisoires exportés en CSV)
- [x] Tests des règles R1 à R4, R32 et R42
- [ ] Code à usage unique (OTP) envoyé par e-mail
- [ ] Réinitialisation du mot de passe par le point focal pour les agents de sa structure (aujourd'hui par l'administrateur)

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
