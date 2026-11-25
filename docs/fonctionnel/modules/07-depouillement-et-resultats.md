# Dépouillement et résultats

**Module de dépouillement et de résultats**

## Objectif

Ce module calcule les résultats à la clôture du scrutin et organise la qualification des lauréates.

## Fonctionnalités

- [x] Le décompte automatique des voix, le calcul des pourcentages, du rang et du taux de participation.
- [x] La détection des égalités et leur traitement selon la modalité de départage qui sera retenue (départage par décision motivée du comité, en attendant la modalité).
- [x] La validation des résultats par le comité, la génération du procès-verbal et la publication.
- [x] La qualification automatique des lauréates vers la phase suivante.
- [x] La proclamation des femmes modèles du Trésor (lauréates de la dernière phase) et l’édition du palmarès (lauréates de chaque phase, par scrutin).

## Avancement

- [x] Migration `proces_verbaux` (un par scrutin, trigger qui interdit toute modification ou suppression après publication, R55) et pourcentage des candidates
- [x] Action `DepouillerScrutin` : contrôle de la chaîne des bulletins (R44) et de sa cohérence avec l'émargement, voix, pourcentage des exprimés, rang partagé en cas d'égalité, lauréates selon le nombre de qualifiées de la phase, quorum (R47, R48, R50, R52)
- [x] Égalité à la limite des places qualificatives signalée et action `TrancherEgalite` : décision motivée du comité, portée au procès-verbal ; la modalité de départage reste à préciser (R49)
- [x] Action `ProlongerScrutin` quand le quorum n'est pas atteint (R50)
- [x] Action `ValiderResultats` : numéro du procès-verbal (`PV-2026-P1-001`) et job `GenererFichierProcesVerbal` (PDF) (R51)
- [x] Action `PublierResultats` : après génération du procès-verbal ; les lauréates publiées sont reprises en phase suivante (R52, R54)
- [x] Femmes modèles du Trésor, lauréates de la dernière phase (`Edition::femmesModeles()`), et palmarès de l'édition (`EtablirPalmares`) (R53)
- [x] Tests des règles R44, R47 à R55
- [x] Procès-verbal en PDF (dompdf) aux en-tête et bas de page de la DGTCP, avec QR code de vérification ; affiché dans le navigateur ou téléchargé ; génération relançable
- [x] Dépouillement automatique à la fermeture (R47), par la commande de bascule lancée chaque minute ; relance possible depuis la fiche du scrutin. Procès-verbal généré avant les annonces par e-mail (file `default` avant `mails`)
- [x] Écrans : fermeture manuelle si l'heure est passée, dépouillement, résultats provisoires réservés au comité et à l'observateur, départage des ex-æquo, prolongation, validation, publication, procès-verbal, palmarès pour tous
- [x] Journalisation de la validation et de la publication (R56, module 08)
- [x] Notification des lauréates à la publication : qualification pour la phase suivante (R35, R52), proclamation des femmes modèles du Trésor à la dernière phase (R53) ; e-mail (file « mails ») et bandeau sur l'accueil

## Acteurs

Comité d'organisation (dépouillement, validation, publication, qualification) ; électeur et observateur (consultation).

## Entités du MCD

CANDIDATE (résultat), PROCES_VERBAL, BULLETIN

## Maquette

- `resources/views/maquettes/resultats.blade.php`, consultable à l'adresse `/maquettes/resultats`

## Écrans

- `/scrutins/{scrutin}` : dépouillement, départage, prolongation, validation, publication, procès-verbal (comité)
- `/palmares` : lauréates publiées et femmes modèles du Trésor (tous)
- `/verification/…` : page publique ouverte par le QR code des PDF

## Règles de gestion

Règles R47 à R55, extraites de [regles-de-gestion.md](../regles-de-gestion.md) :

### Dépouillement et résultats

- **R47** · À la clôture, le système calcule pour chaque candidate le nombre de voix, le pourcentage des suffrages exprimés et le rang, ainsi que le nombre d’inscrits, de votants, de bulletins blancs et le taux de participation.
- **R48** · Sont lauréates du scrutin les candidates ayant obtenu le plus grand nombre de voix, autant que son collège compte d’élues.
- **R49** · En cas d’égalité de voix à la dernière place d’élue, le comité départage les ex-æquo par une décision motivée, portée au procès-verbal ; la validation des résultats attend ce départage (modalité à confirmer par le métier).
- **R50** · Un quorum de participation peut être fixé pour l’édition ; il s’applique à chacun de ses scrutins. S’il n’est pas atteint à la fermeture, le comité peut prolonger le scrutin.
- **R51** · Les résultats sont validés par le comité d’organisation ; un procès-verbal numéroté est généré pour chaque scrutin puis les résultats sont publiés.
- **R52** · Après publication, les lauréates d’un scrutin, autant que son collège compte d’élues, sont qualifiées pour la phase suivante.
- **R53** · Les lauréates de la dernière phase sont proclamées « Femme modèle du Trésor » de l’édition : une par élue de chaque scrutin de cette phase.
- **R54** · Les résultats d’une phase deviennent définitifs dès leur validation et leur publication par le comité d’organisation.
- **R55** · Les résultats publiés et les procès-verbaux sont archivés et ne peuvent plus être modifiés.
