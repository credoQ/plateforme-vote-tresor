# Vote

**Module de vote**

## Objectif

Ce module permet aux électeurs de voter en ligne pendant la période d’ouverture de chaque scrutin.

## Fonctionnalités

- [ ] L’affichage, pour chaque électeur, du seul scrutin auquel il est inscrit pour la phase en cours.
- [ ] La consultation de la liste des candidates du scrutin (nom, prénoms, structure), avec recherche par nom et par structure.
- [ ] Le désistement en ligne d’une candidate depuis son espace personnel, avant l’ouverture du scrutin.
- [ ] Le vote avec confirmation, puis délivrance d’un accusé de vote sans indication du choix exprimé.
- [ ] L’enregistrement séparé de l’émargement (qui a voté) et du bulletin (pour qui), garantissant le secret du vote.
- [ ] L’accès depuis un ordinateur ou un téléphone mobile (interface adaptative).

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
- **R42** · Le vote nécessite une authentification par matricule et mot de passe personnel ; un code OTP peut être exigé en complément lorsque l’agent dispose d’un contact renseigné.
- **R43** · Le vote est secret : l’émargement (agent, scrutin, date-heure) et le bulletin (scrutin, candidate) sont enregistrés séparément, dans une même transaction, sans aucun lien entre eux ; l’horodatage du bulletin est volontairement imprécis afin d’empêcher tout rapprochement.
- **R44** · Chaque bulletin porte une empreinte cryptographique chaînée permettant de détecter toute altération.
- **R45** · Une candidate est électrice dans son scrutin au même titre que les autres agents (le vote pour soi-même est à confirmer par le métier).
- **R46** · Pendant le vote, seul le taux de participation est visible ; le nombre de voix par candidate n’est accessible qu’après la clôture.
