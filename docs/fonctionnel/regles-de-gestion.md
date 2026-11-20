# Règles de gestion

Règles de gestion de la plateforme de vote « Femme modèle du Trésor ».
Elles reprennent la section 6 du document technique V1. Leur numéro (R1 à R59) sert de référence dans le code et dans les tests.

Une règle qui dépend d'un point encore à valider doit rester paramétrable : voir la fin de ce fichier.

## Sommaire

1. [Gestion des utilisateurs et des rôles](#gestion-des-utilisateurs-et-des-roles) : R1 à R4
2. [Gestion du référentiel des structures](#gestion-du-referentiel-des-structures) : R5 à R10
3. [Gestion des agents et des affectations](#gestion-des-agents-et-des-affectations) : R11 à R15
4. [Gestion des éditions, des phases et des scrutins](#gestion-des-editions-des-phases-et-des-scrutins) : R16 à R23
5. [Circonscriptions et listes électorales](#circonscriptions-et-listes-electorales) : R24 à R27
6. [Candidates](#candidates) : R28 à R38, R58, R59
7. [Vote](#vote) : R39 à R46
8. [Dépouillement et résultats](#depouillement-et-resultats) : R47 à R55
9. [Sécurité et traçabilité](#securite-et-tracabilite) : R56 à R57
10. [Points à valider et questions à éclaircir](#points-a-valider-et-questions-a-eclaircir)

## Gestion des utilisateurs et des rôles

- **R1** · Un utilisateur est identifié par un id et possède un login (le matricule pour un agent), une adresse e-mail (la sienne ou celle de sa fiche agent) qui reçoit ses codes de connexion, un statut (actif/inactif) et une date de dernière connexion. Il n’a pas de mot de passe.
- **R2** · Un utilisateur possède un ou plusieurs rôles parmi : Administrateur, Comité d’organisation, Point focal, Électeur, Observateur.
- **R3** · Un agent dispose d’au plus un compte utilisateur ; un compte peut ne pas être lié à un agent (administrateur technique, observateur externe).
- **R4** · Le point focal n’intervient que sur les agents et la liste électorale de la structure à laquelle il est rattaché.

## Gestion du référentiel des structures

- **R5** · Une structure est identifiée par un id et possède un code, un sigle, un libellé, un niveau et un statut ; elle est d’un et un seul type de structure.
- **R6** · Un pôle électoral est identifié par un id et possède un code et un libellé ; il en existe deux : le pôle départemental et le pôle structures.
- **R7** · Un type de structure est défini par un code et un libellé, et il est classé dans un et un seul pôle électoral ; un pôle regroupe un ou plusieurs types. Les types sont : Cellule, Direction centrale, TMIE et Agence comptable de la Dette (ACD), classés dans le pôle structures ; Trésorerie départementale (TD) et Trésorerie communale (TC), classées dans le pôle départemental.
- **R8** · Une structure peut être rattachée à une structure mère (un service à sa direction, une Trésorerie communale à sa Trésorerie départementale).
- **R9** · Chaque Trésorerie départementale et chaque Trésorerie communale est située dans l’un des 12 départements.
- **R10** · Chaque structure est autonome et constitue sa propre structure électorale (directions, cellules, TMIE, ACD). Le Cabinet du Directeur général regroupe le Directeur général, son adjoint, l’Assistant du Directeur général et les secrétariats ; les cellules et l’Inspection générale des services sont des structures autonomes, non rattachées au Cabinet, avec leur propre point focal.

## Gestion des agents et des affectations

- **R11** · Un agent est identifié par son matricule, unique ; il possède un NPI, un nom, des prénoms, un sexe et une date de naissance. La date de prise de service, le téléphone, l’e-mail, la fonction et le statut peuvent figurer dans la fiche à titre informatif, mais ne sont pas gérés par la plateforme : aucune règle ne s’appuie sur eux.
- **R12** · Un agent est affecté à une seule structure à une date donnée ; l’historique des affectations est conservé.
- **R13** · Un agent est considéré en service lorsqu’il figure dans la liste des agents avec une affectation en cours dans une structure de la DGTCP ; la tenue à jour de cette liste relève de l’import des agents et des affectations.
- **R14** · Est électeur tout agent en service à la date de gel des listes électorales (les catégories exactes d’agents admis à voter sont à confirmer par le métier).
- **R15** · Les mutations intervenues après la date de gel ne modifient pas les listes électorales de l’édition en cours.

## Gestion des éditions, des phases et des scrutins

- **R16** · Une édition est identifiée par un id et possède une année, un libellé, des dates de début et de fin, une date de gel des listes et un statut ; une seule édition peut être en cours.
- **R17** · Une édition comporte une à trois phases ordonnées, paramétrées par le comité à la création de l’édition : le libellé de chaque phase et ses collèges (R20). Ce paramétrage se modifie tant que l’édition est en préparation et qu’aucun scrutin n’est généré ; il est ensuite figé. L’organisation proposée par défaut est décrite en R23.
- **R18** · Une phase ne peut être ouverte que si les résultats de tous les scrutins de la phase précédente ont été validés et publiés.
- **R19** · Une phase comporte un ou plusieurs scrutins ; un scrutin appartient à une seule phase et possède une date-heure d’ouverture et de fermeture.
- **R20** · Chaque phase déclare un ou plusieurs collèges : le pôle départemental, le pôle structures, ou l’ensemble de la DGTCP, qui est alors le seul collège de la phase. Pour chaque collège, le comité fixe le découpage (un scrutin par circonscription, c’est-à-dire par département ou par structure électorale, ou un seul scrutin pour tout le collège), l’origine des candidates (R28) et le nombre d’élues par scrutin (R52).
- **R21** · Une structure dont le pôle n’a pas de collège dans une phase n’y a pas de scrutin : ses agents ne votent pas dans cette phase.
- **R22** · Un scrutin relève d’au plus un pôle électoral, celui de son collège ; un scrutin de l’ensemble de la DGTCP ne relève d’aucun pôle. Le corps électoral d’un scrutin d’un seul tenant pour un pôle est constitué des agents des structures de ce pôle.
- **R23** · Organisation proposée par défaut : phase 1 (primaires), un scrutin par département et par structure électorale, candidates d’office ; phase 2 (demi-finales), un scrutin par pôle, lauréates qualifiées ; phase 3 (finale), un scrutin pour l’ensemble de la DGTCP, lauréates qualifiées ; une élue par scrutin.

## Circonscriptions et listes électorales

- **R24** · La circonscription d’un scrutin est l’ensemble des structures qu’il couvre ; la liste électorale est constituée des agents électeurs affectés à ces structures :
  - scrutin départemental : la Trésorerie départementale et les Trésoreries communales du département ;
  - scrutin de structure : la structure électorale (y compris ses services) ;
  - scrutin du pôle départemental : toutes les Trésoreries départementales et Trésoreries communales ;
  - scrutin du pôle structures : toutes les structures du pôle structures (y compris les TMIE et l’ACD) ;
  - scrutin de l’ensemble de la DGTCP : toutes les structures de la DGTCP.
- **R25** · Un agent vote dans la circonscription de son poste d’affectation : les agents d’une structure centrale en poste dans une Trésorerie départementale (par exemple les antennes de la DIA) votent avec le département.
- **R26** · Pour une phase donnée, un agent est inscrit sur au plus une liste électorale ; il vote donc au plus une fois par phase (il ne vote pas dans une phase où sa structure n’a pas de scrutin).
- **R27** · Les listes électorales sont arrêtées par le comité avant l’ouverture du scrutin et ne peuvent plus être modifiées, sauf décision motivée du comité tracée au journal.

## Candidates

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

## Vote

- **R39** · Le vote est électronique et n’est possible que pendant la période d’ouverture du scrutin.
- **R40** · Un électeur ne vote qu’une seule fois par scrutin et uniquement dans le scrutin où il est inscrit ; le vote est définitif et ne peut être modifié.
- **R41** · Le scrutin est uninominal à un tour : l’électeur choisit une seule candidate. Le vote blanc est paramétrable par édition.
- **R42** · L’authentification se fait sans mot de passe : l’agent saisit son matricule et reçoit par e-mail un code à usage unique (OTP), valable 10 minutes, qu’il saisit pour se connecter. Le compte doit avoir une adresse e-mail (celle du compte ou de la fiche agent).
- **R43** · Le vote est secret : l’émargement (agent, scrutin, date-heure) et le bulletin (scrutin, candidate) sont enregistrés séparément, dans une même transaction, sans aucun lien entre eux ; l’horodatage du bulletin est volontairement imprécis afin d’empêcher tout rapprochement.
- **R44** · Chaque bulletin porte une empreinte cryptographique chaînée permettant de détecter toute altération.
- **R45** · Une candidate est électrice dans son scrutin au même titre que les autres agents (le vote pour soi-même est à confirmer par le métier).
- **R46** · Pendant le vote, seul le taux de participation est visible ; le nombre de voix par candidate n’est accessible qu’après la clôture.

## Dépouillement et résultats

- **R47** · À la clôture, le système calcule pour chaque candidate le nombre de voix, le pourcentage des suffrages exprimés et le rang, ainsi que le nombre d’inscrits, de votants, de bulletins blancs et le taux de participation.
- **R48** · Sont lauréates du scrutin les candidates ayant obtenu le plus grand nombre de voix, autant que son collège compte d’élues.
- **R49** · En cas d’égalité de voix à la dernière place d’élue, le comité départage les ex-æquo par une décision motivée, portée au procès-verbal ; la validation des résultats attend ce départage (modalité à confirmer par le métier).
- **R50** · Un quorum de participation peut être fixé pour l’édition ; il s’applique à chacun de ses scrutins. S’il n’est pas atteint à la fermeture, le comité peut prolonger le scrutin.
- **R51** · Les résultats sont validés par le comité d’organisation ; un procès-verbal numéroté est généré pour chaque scrutin puis les résultats sont publiés.
- **R52** · Après publication, les lauréates d’un scrutin, autant que son collège compte d’élues, sont qualifiées pour la phase suivante.
- **R53** · Les lauréates de la dernière phase sont proclamées « Femme modèle du Trésor » de l’édition : une par élue de chaque scrutin de cette phase.
- **R54** · Les résultats d’une phase deviennent définitifs dès leur validation et leur publication par le comité d’organisation.
- **R55** · Les résultats publiés et les procès-verbaux sont archivés et ne peuvent plus être modifiés.

## Sécurité et traçabilité

- **R56** · Toutes les opérations sensibles (demande de code et connexion, gestion des comptes, des structures et des agents, import des agents, ajout et retrait dans l’historique des femmes modèles, génération, arrêt et réouverture des listes, nomination et retrait de nomination, désactivation ou réactivation de candidate, ouverture et clôture des phases et des scrutins, dépouillement, départage, validation et publication des résultats, relance et suppression des tâches en échec) sont enregistrées dans un journal d’audit non modifiable (utilisateur, date-heure, action, entité, adresse IP). Le vote lui-même n’y figure jamais.
- **R57** · Aucun utilisateur, y compris l’administrateur, ne peut consulter le choix d’un électeur ni modifier un bulletin.

## Points à valider et questions à éclaircir

Ces points ont fait l'objet d'une proposition. Ils ne doivent pas être codés en dur tant qu'ils ne sont pas tranchés. Entre parenthèses : le traitement actuel dans la plateforme.

### Points à confirmer par le métier

- Le traitement des TMIE (une circonscription par TMIE). *(Prévu ainsi ; la liste des TMIE reste à importer.)*
- Les catégories d’agents admis à voter (fonctionnaires, contractuels, stagiaires, agents en détachement ou en congé). *(Tout agent en service à la date de gel vote.)*
- Le nombre de lauréates qualifiées par scrutin pour la phase suivante (une par défaut). *(Paramètre de chaque collège, R20.)*
- L’autorisation du vote blanc et l’existence d’un quorum. *(Paramètres de l’édition : vote blanc refusé et aucun quorum par défaut.)*
- La possibilité pour une candidate de voter pour elle-même. *(Paramètre de l’édition, autorisé par défaut.)*
- Le remplacement d’une lauréate qui se désiste par la candidate classée immédiatement après elle. *(Non développé.)*
- La composition du comité d’organisation. *(Les membres sont les comptes ayant le rôle Comité.)*

### Questions à éclaircir

- L’Inspection générale des services, la Cellule des réformes et de la démarche qualité et la Cellule de sécurité des systèmes d’information auront-elles chacune leur propre scrutin, ou seront-elles incluses dans le scrutin du Cabinet du Directeur général ? *(Chacune a son propre scrutin.)*
- Comment départager les ex-æquo en cas d’égalité à la dernière place d’élue : second tour limité aux ex-æquo, ou un autre critère ? *(Le comité retient les lauréates parmi les ex-æquo par une décision motivée, portée au procès-verbal.)*
- Quel type de structure attribuer au Cabinet du Directeur général et à l’Inspection générale des services (proposition : Direction centrale pour le Cabinet, Cellule pour l’IGS) ? *(Appliqué ainsi.)*
- Lorsqu’une structure ne compte qu’une seule agent femme en service, le scrutin est-il maintenu ou l’agent est-elle désignée lauréate d’office ? *(Le scrutin est maintenu.)*
