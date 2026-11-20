# Scrutins, listes électorales et candidates

**Module de gestion des scrutins et des listes électorales**

## Objectif

Ce module génère les scrutins de chaque phase et leurs corps électoraux.

## Fonctionnalités

- [x] La génération automatique des scrutins de chaque phase selon ses collèges : un scrutin par département ou par structure électorale, ou un seul scrutin par pôle ou pour toute la DGTCP ; pour des candidates d’office, les circonscriptions sans agent femme en service sont écartées et signalées au comité.
- [x] La définition de la circonscription de chaque scrutin (ensemble des structures couvertes).
- [x] La constitution automatique des listes électorales à partir des affectations à la date de gel.
- [x] Le contrôle des listes par les points focaux, les corrections et l’arrêt définitif des listes par le comité.
- [x] La constitution de la liste des candidates de chaque scrutin selon son collège : d’office (agents de sexe féminin en service dans la circonscription), par nomination du comité, ou par reprise des lauréates qualifiées de la phase précédente.
- [x] La désactivation d’une candidate qui ne souhaite pas concourir (désistement), par le point focal ou le comité à sa demande, et sa réactivation éventuelle avant l’ouverture du scrutin.
- [x] La publication et l’export des listes électorales et des listes de candidates.

## Avancement

- [x] Migrations `couvertures` (circonscriptions), `inscriptions` (un agent inscrit au plus une fois par phase, R26) et `candidates` (statut, désistement tracé, lien de qualification)
- [x] Enums `OrigineCandidate` et `StatutCandidate`, modèles `Inscription` et `Candidate`
- [x] Action `DecouperCirconscriptions` : département pour les structures situées dans un département (TD, TC, antennes, R25), structure électorale sinon ; puis découpage selon les collèges de la phase : par circonscription, par pôle, ou toute la DGTCP
- [x] Action `GenererScrutins` : scrutins de chaque phase, circonscriptions sans agent femme écartées et signalées pour un collège aux candidates d'office (R20, R37)
- [x] Action `ConstituerListes` : électeurs à la date de gel (R14, R15, R29), candidates selon le collège : d'office (R28), nominées conservées (R58), lauréates de la phase précédente (R35) ; actualisable jusqu'à l'arrêt
- [x] Action `ArreterListes` : arrêt à partir de la date de gel, listes figées ensuite (R27, R30)
- [x] Actions `DesactiverCandidate` et `ReactiverCandidate` : jusqu'à l'ouverture du scrutin, avec date, auteur et motif (R32 à R34)
- [x] Tests des règles R14, R15, R20 à R30, R32 à R35 et R37
- [x] Écrans : génération depuis l'édition, fiche du scrutin (planification à l'heure du Bénin, actualisation et arrêt des listes, candidates, liste électorale avec recherche, export) ; le point focal consulte et désactive dans sa structure
- [x] Corrections motivées des listes après l'arrêt (R27) : réouverture par le comité avec motif tracé au journal, avant l'ouverture du scrutin, puis correction des agents, actualisation et nouvel arrêt
- [x] Annulation d'un scrutin dont toutes les candidates sont désactivées (R38), à l'ouverture du scrutin (module 06)
- [x] Nomination par le comité (R28, R58) : actions `NominerCandidate` et `RetirerNomination`, saisie du matricule et de la référence de la décision sur la fiche du scrutin, jusqu'à l'arrêt des listes, sans limite de nombre ; tracées au journal
- [x] Inéligibilité (R59) : délai de rééligibilité paramétré sur l'édition ; historique `femmes_modeles` alimenté à la clôture de la dernière phase et complété par saisie du comité (bouton « Ajouter une femme modèle ») pour les éditions antérieures ; femme modèle inéligible écartée des candidates d'office et refusée à la nomination, signalée sur la fiche du scrutin et sur sa fiche agent ; écran `/femmes-modeles` (comité)
- [ ] Remplacement d'une lauréate qui se désiste (R36), point à confirmer par le métier
- [x] Exports CSV, Excel et PDF des listes, avec QR code de vérification sur le PDF (module 08)

## Acteurs

Comité d'organisation (génération, arrêt des listes, désactivation) ; point focal (contrôle et désactivation pour sa structure).

## Entités du MCD

SCRUTIN, COUVRIR, INSCRIRE (liste électorale), CANDIDATE, QUALIFIER

## Maquette

- `resources/views/maquettes/candidates.blade.php`, consultable à l'adresse `/maquettes/candidates`

## Écrans

- `/editions/{édition}` : génération des scrutins d'une phase (comité)
- `/scrutins/{scrutin}` : planification, listes, candidates, arrêt, exports (comité ; point focal pour sa structure)

## Règles de gestion

Règles R24 à R27, R28 à R38, R58 et R59, extraites de [regles-de-gestion.md](../regles-de-gestion.md) :

### Circonscriptions et listes électorales

- **R24** · La circonscription d’un scrutin est l’ensemble des structures qu’il couvre ; la liste électorale est constituée des agents électeurs affectés à ces structures :
  - scrutin départemental : la Trésorerie départementale et les Trésoreries communales du département ;
  - scrutin de structure : la structure électorale (y compris ses services) ;
  - scrutin du pôle départemental : toutes les Trésoreries départementales et Trésoreries communales ;
  - scrutin du pôle structures : toutes les structures du pôle structures (y compris les TMIE et l’ACD) ;
  - scrutin de l’ensemble de la DGTCP : toutes les structures de la DGTCP.
- **R25** · Un agent vote dans la circonscription de son poste d’affectation : les agents d’une structure centrale en poste dans une Trésorerie départementale (par exemple les antennes de la DIA) votent avec le département.
- **R26** · Pour une phase donnée, un agent est inscrit sur au plus une liste électorale ; il vote donc au plus une fois par phase (il ne vote pas dans une phase où sa structure n’a pas de scrutin).
- **R27** · Les listes électorales sont arrêtées par le comité avant l’ouverture du scrutin et ne peuvent plus être modifiées, sauf décision motivée du comité tracée au journal.

### Candidates

- **R28** · Il n’y a pas de dépôt de candidature. Selon le collège du scrutin, les candidates sont : d’office, toutes les agents de sexe féminin en service dans les structures de la circonscription à la date de gel des listes ; nominées, celles que le comité saisit sur décision (procès-verbal, fiche de nomination) ; qualifiées, les lauréates de la phase précédente (R35).
- **R29** · Une agent qui n’a pas d’affectation en cours à la date de gel n’est ni électrice ni candidate.
- **R30** · La liste des candidates d’un scrutin se constitue selon son collège (d’office, par qualification ou par nomination) et se fige à l’arrêt des listes électorales ; elle ne peut ensuite être modifiée que par la désactivation ou la réactivation d’une candidate, sauf réouverture motivée des listes (R27).
- **R31** · Une candidate est rattachée à une seule agent et à un seul scrutin ; elle a un statut (Active ou Désactivée) et porte son résultat (nombre de voix, rang, indicateur de lauréate).
- **R32** · Une agent qui ne souhaite pas concourir peut être désactivée : soit par elle-même depuis son espace (désistement), soit par le point focal de sa structure ou par le comité d’organisation, à sa demande.
- **R33** · La désactivation n’est possible que jusqu’à l’ouverture du scrutin ; elle est tracée (date, auteur, motif facultatif). Une candidate désactivée n’apparaît plus sur la liste des candidates et ne peut recevoir aucune voix ; elle reste électrice.
- **R34** · Une candidate désactivée peut être réactivée, par les mêmes acteurs, tant que le scrutin n’est pas ouvert.
- **R35** · Pour un collège à qualification, les candidates sont les lauréates de la phase précédente pour le même pôle (toutes les lauréates de la phase précédente pour un collège de l’ensemble de la DGTCP) ; chaque candidate conserve le lien avec sa participation d’origine. La qualification n’est pas possible en phase 1.
- **R36** · Une lauréate qualifiée peut se désister avant l’ouverture de la phase suivante ; elle est alors remplacée par la candidate classée immédiatement après elle dans son scrutin d’origine.
- **R37** · Pour un collège aux candidates d’office, aucun scrutin n’est généré pour une circonscription qui ne compte aucune agent de sexe féminin en service à la date de gel : elle n’a pas de lauréate et ses agents ne votent pas dans cette phase.
- **R38** · Si toutes les candidates d’un scrutin sont désactivées avant son ouverture, le scrutin est annulé.
- **R58** · Pour un collège à nomination, le comité saisit les nominées de chaque scrutin jusqu’à l’arrêt des listes : une agent de sexe féminin inscrite sur la liste électorale du scrutin, avec la référence de la décision (procès-verbal, fiche de nomination). Le nombre de nominées n’est pas limité. Il peut retirer une nomination saisie par erreur dans les mêmes conditions. Chaque nomination et chaque retrait sont tracés au journal.
- **R59** · Une femme modèle du Trésor élue l’année A ne peut être de nouveau candidate, d’office ou nominée, à l’édition de l’année N tant que N − A ≤ délai de rééligibilité, paramètre de l’édition (3 ans par défaut) : élue en 2024 avec un délai de 3 ans, elle est de nouveau candidate en 2028. Elle reste électrice. L’historique des femmes modèles est alimenté à la clôture de la dernière phase de chaque édition et complété par le comité, femme modèle par femme modèle, pour les éditions antérieures à la plateforme.
