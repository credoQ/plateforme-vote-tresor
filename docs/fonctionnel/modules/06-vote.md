# Vote

**Module de vote**

## Objectif

Ce module permet aux électeurs de voter en ligne pendant la période d’ouverture de chaque scrutin.

## Fonctionnalités

- [x] L’affichage, pour chaque électeur, du seul scrutin auquel il est inscrit pour la phase en cours.
- [x] La consultation de la liste des candidates du scrutin (nom, prénoms, structure), avec recherche par nom et par structure.
- [x] Le désistement en ligne d’une candidate depuis son espace personnel, avant l’ouverture du scrutin.
- [x] Le vote avec confirmation, puis délivrance d’un accusé de vote sans indication du choix exprimé.
- [x] L’enregistrement séparé de l’émargement (qui a voté) et du bulletin (pour qui), garantissant le secret du vote.
- [x] L’accès depuis un ordinateur ou un téléphone mobile (interface adaptative).

## Avancement

- [x] Migration `bulletins` : identifiant aléatoire, aucune colonne vers l'agent ou l'utilisateur, pas de `created_at`, trigger qui interdit toute modification ou suppression (R43, R44)
- [x] Paramètre `vote_pour_soi_autorise` de l'édition (R45, à confirmer) et numéro d'accusé de vote sur l'inscription
- [x] Actions `PlanifierScrutin`, `OuvrirScrutin` (annulation si aucune candidate active, R38) et `FermerScrutin` ; commande `scrutins:basculer` lancée chaque minute par le planificateur (R39)
- [x] Action `DeposerBulletin` : vote unique et définitif dans son scrutin (R40), choix d'une candidate active ou vote blanc paramétré (R33, R41), émargement et bulletin dans une transaction, horodatages tronqués à l'heure (R43), accusé de vote sans le choix
- [x] Empreinte chaînée HMAC-SHA256 et vérification de la chaîne (R44)
- [x] Taux de participation, seule donnée visible pendant le vote (R46)
- [x] Tests des règles R33, R38 à R41 et R43 à R46
- [x] Écrans de l'électeur : bulletin avec recherche, confirmation en deux étapes sans JavaScript, accusé imprimable sans le choix, retrait et réactivation de candidature depuis l'accueil (R32 à R34, R40, R42)

## Acteurs

Électeur ; candidate (désistement depuis son espace).

## Entités du MCD

INSCRIRE (émargement), BULLETIN, CANDIDATE

## Maquette

- `resources/views/maquettes/vote.blade.php`, consultable à l'adresse `/maquettes/vote`

## Règles de gestion

Règles R39 à R46, extraites de [regles-de-gestion.md](../regles-de-gestion.md) :

### Vote

- **R39** · Le vote est électronique et n’est possible que pendant la période d’ouverture du scrutin.
- **R40** · Un électeur ne vote qu’une seule fois par scrutin et uniquement dans le scrutin où il est inscrit ; le vote est définitif et ne peut être modifié.
- **R41** · Le scrutin est uninominal à un tour : l’électeur choisit une seule candidate. Le vote blanc est paramétrable par édition.
- **R42** · Le vote nécessite une authentification par matricule et mot de passe personnel ; un code à usage unique (OTP), envoyé par e-mail, peut être exigé en complément lorsque l’agent dispose d’une adresse e-mail.
- **R43** · Le vote est secret : l’émargement (agent, scrutin, date-heure) et le bulletin (scrutin, candidate) sont enregistrés séparément, dans une même transaction, sans aucun lien entre eux ; l’horodatage du bulletin est volontairement imprécis afin d’empêcher tout rapprochement.
- **R44** · Chaque bulletin porte une empreinte cryptographique chaînée permettant de détecter toute altération.
- **R45** · Une candidate est électrice dans son scrutin au même titre que les autres agents (le vote pour soi-même est à confirmer par le métier).
- **R46** · Pendant le vote, seul le taux de participation est visible ; le nombre de voix par candidate n’est accessible qu’après la clôture.
