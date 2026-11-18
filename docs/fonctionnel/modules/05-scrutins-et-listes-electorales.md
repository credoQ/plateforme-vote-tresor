# Scrutins, listes électorales et candidates

**Module de gestion des scrutins et des listes électorales**

## Objectif

Ce module génère les scrutins de chaque phase et leurs corps électoraux.

## Fonctionnalités

- [ ] La génération automatique des scrutins de la phase 1 (12 scrutins départementaux et un scrutin par structure électorale comptant au moins une agent femme en service ; les structures sans agent femme sont signalées au comité), de la phase 2 (deux demi-finales) et de la phase 3 (finale).
- [ ] La définition de la circonscription de chaque scrutin (ensemble des structures couvertes).
- [ ] La constitution automatique des listes électorales à partir des affectations à la date de gel.
- [ ] Le contrôle des listes par les points focaux, les corrections et l’arrêt définitif des listes par le comité.
- [ ] La constitution automatique de la liste des candidates de chaque scrutin de la phase 1 (agents de sexe féminin en service dans la circonscription) et de la reprise des lauréates qualifiées dans les phases 2 et 3.
- [ ] La désactivation d’une candidate qui ne souhaite pas concourir (désistement), par le point focal ou le comité à sa demande, et sa réactivation éventuelle avant l’ouverture du scrutin.
- [ ] La publication et l’export des listes électorales et des listes de candidates.

## Avancement

- [x] Migrations `couvertures` (circonscriptions), `inscriptions` (un agent inscrit au plus une fois par phase, R26) et `candidates` (statut, désistement tracé, lien de qualification)
- [x] Enums `OrigineCandidate` et `StatutCandidate`, modèles `Inscription` et `Candidate`
- [x] Action `DecouperCirconscriptions` : département pour les structures situées dans un département (TD, TC, antennes, R25), structure électorale sinon ; un pôle par demi-finale ; toute la DGTCP en finale
- [x] Action `GenererScrutins` : scrutins de chaque phase, circonscriptions sans agent femme écartées et signalées en phase 1 (R20, R37)
- [x] Action `ConstituerListes` : électeurs à la date de gel (R14, R15, R29), candidates d'office en phase 1 (R28), lauréates de la phase précédente en phases 2 et 3 (R35) ; actualisable jusqu'à l'arrêt
- [x] Action `ArreterListes` : arrêt à partir de la date de gel, listes figées ensuite (R27, R30)
- [x] Actions `DesactiverCandidate` et `ReactiverCandidate` : jusqu'à l'ouverture du scrutin, avec date, auteur et motif (R32 à R34)
- [x] Tests des règles R14, R15, R20 à R30, R32 à R35 et R37
- [ ] Contrôle des listes par les points focaux et corrections motivées après l'arrêt (droits du module 01, journal du module 08)
- [ ] Annulation d'un scrutin dont toutes les candidates sont désactivées (R38), à l'ouverture du scrutin (module 06)
- [ ] Remplacement d'une lauréate qui se désiste (R36), point à confirmer par le métier
- [ ] Publication et export des listes

## Acteurs

Comité d'organisation (génération, arrêt des listes, désactivation) ; point focal (contrôle et désactivation pour sa structure).

## Entités du MCD

SCRUTIN, COUVRIR, INSCRIRE (liste électorale), CANDIDATE, QUALIFIER

## Maquette

- `resources/views/maquettes/candidates.blade.php`, consultable à l'adresse `/maquettes/candidates`

## Règles de gestion

Règles R24 à R27, R28 à R38, extraites de [regles-de-gestion.md](../regles-de-gestion.md) :

### Circonscriptions et listes électorales

- **R24** · La circonscription d’un scrutin est l’ensemble des structures qu’il couvre ; la liste électorale est constituée des agents électeurs affectés à ces structures :
  - scrutin départemental : la Trésorerie départementale et les Trésoreries communales du département ;
  - scrutin de structure : la structure électorale (y compris ses services) ;
  - demi-finale départementale : toutes les Trésoreries départementales et Trésoreries communales ;
  - demi-finale des structures : toutes les structures du pôle structures (y compris les TMIE et l’ACD) ;
  - finale : toutes les structures de la DGTCP.
- **R25** · Un agent vote dans la circonscription de son poste d’affectation : les agents d’une structure centrale en poste dans une Trésorerie départementale (par exemple les antennes de la DIA) votent avec le département.
- **R26** · Pour une phase donnée, un agent est inscrit sur au plus une liste électorale ; il vote donc au plus une fois par phase (il ne vote pas en phase 1 si sa structure n’a pas de scrutin).
- **R27** · Les listes électorales sont arrêtées par le comité avant l’ouverture du scrutin et ne peuvent plus être modifiées, sauf décision motivée du comité tracée au journal.

### Candidates

- **R28** · Il n’y a pas de dépôt de candidature : en phase 1, toutes les agents de sexe féminin en service dans les structures de la circonscription d’un scrutin, à la date de gel des listes, sont automatiquement candidates à ce scrutin.
- **R29** · Une agent qui n’a pas d’affectation en cours à la date de gel n’est ni électrice ni candidate.
- **R30** · La liste des candidates d’un scrutin est constituée automatiquement à l’arrêt des listes électorales ; elle ne peut ensuite être modifiée que par la désactivation ou la réactivation d’une candidate.
- **R31** · Une candidate est rattachée à une seule agent et à un seul scrutin ; elle a un statut (Active ou Désactivée) et porte son résultat (nombre de voix, rang, indicateur de lauréate).
- **R32** · Une agent qui ne souhaite pas concourir peut être désactivée : soit par elle-même depuis son espace (désistement), soit par le point focal de sa structure ou par le comité d’organisation, à sa demande.
- **R33** · La désactivation n’est possible que jusqu’à l’ouverture du scrutin ; elle est tracée (date, auteur, motif facultatif). Une candidate désactivée n’apparaît plus sur la liste des candidates et ne peut recevoir aucune voix ; elle reste électrice.
- **R34** · Une candidate désactivée peut être réactivée, par les mêmes acteurs, tant que le scrutin n’est pas ouvert.
- **R35** · En phases 2 et 3, les candidates sont les lauréates qualifiées de la phase précédente ; chaque candidate conserve le lien avec sa participation d’origine.
- **R36** · Une lauréate qualifiée peut se désister avant l’ouverture de la phase suivante ; elle est alors remplacée par la candidate classée immédiatement après elle dans son scrutin d’origine.
- **R37** · Aucun scrutin n’est généré pour une structure qui ne compte aucune agent de sexe féminin en service à la date de gel : la structure n’a pas de lauréate et ses agents ne votent pas en phase 1 ; ils votent normalement en phases 2 et 3.
- **R38** · Si toutes les candidates d’un scrutin de la phase 1 sont désactivées avant son ouverture, le scrutin est annulé dans les mêmes conditions.
