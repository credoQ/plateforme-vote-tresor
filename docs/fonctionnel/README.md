# Modules fonctionnels

Un fichier par module de la plateforme. Chaque fichier donne l'objectif du module, ses fonctionnalités, ses acteurs, ses entités du MCD, ses écrans, la maquette de référence et les règles de gestion qui le concernent.

| Module | Règles | Écrans |
| --- | --- | --- |
| [Utilisateurs et accès](modules/01-utilisateurs-et-acces.md) | R1 à R4, R42 | `/connexion`, `/utilisateurs` |
| [Référentiel des structures](modules/02-referentiel-des-structures.md) | R5 à R10 | · |
| [Agents et affectations](modules/03-agents.md) | R11 à R15 | `/agents` |
| [Éditions et phases](modules/04-editions-et-phases.md) | R16 à R23 | `/editions` |
| [Scrutins, listes électorales et candidates](modules/05-scrutins-et-listes-electorales.md) | R24 à R27, R28 à R38 | `/scrutins/{scrutin}` |
| [Vote](modules/06-vote.md) | R39 à R46 | `/vote` |
| [Dépouillement et résultats](modules/07-depouillement-et-resultats.md) | R47 à R55 | `/scrutins/{scrutin}`, `/palmares` |
| [Tableau de bord, notifications et audit](modules/08-tableau-de-bord-notifications-et-audit.md) | R56 à R57 | `/tableau-de-bord`, `/journal`, `/taches-echouees` |

Les cases à cocher des fonctionnalités servent au suivi de l'implémentation. Les maquettes statiques de la charte restent consultables à l'adresse `/maquettes`.
Les règles de gestion complètes et les points encore à valider sont dans [regles-de-gestion.md](regles-de-gestion.md).
