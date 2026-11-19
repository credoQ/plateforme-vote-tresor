# Dépouillement et résultats

**Module de dépouillement et de résultats**

## Objectif

Ce module calcule les résultats à la clôture du scrutin et organise la qualification des lauréates.

## Fonctionnalités

- [x] Le décompte automatique des voix, le calcul des pourcentages, du rang et du taux de participation.
- [x] La détection des égalités et leur traitement selon la modalité de départage qui sera retenue (départage par décision motivée du comité, en attendant la modalité).
- [x] La validation des résultats par le comité, la génération du procès-verbal et la publication.
- [x] La qualification automatique des lauréates vers la phase suivante.
- [x] La proclamation de la Femme modèle du Trésor et l’édition du palmarès (lauréates départementales, de structure et de pôle).

## Avancement

- [x] Migration `proces_verbaux` (un par scrutin, trigger qui interdit toute modification ou suppression après publication, R55) et pourcentage des candidates
- [x] Action `DepouillerScrutin` : contrôle de la chaîne des bulletins (R44) et de sa cohérence avec l'émargement, voix, pourcentage des exprimés, rang partagé en cas d'égalité, lauréates selon le nombre de qualifiées de la phase, quorum (R47, R48, R50, R52)
- [x] Égalité à la limite des places qualificatives signalée et action `TrancherEgalite` : décision motivée du comité, portée au procès-verbal ; la modalité de départage reste à préciser (R49)
- [x] Action `ProlongerScrutin` quand le quorum n'est pas atteint (R50)
- [x] Action `ValiderResultats` : numéro du procès-verbal (`PV-2026-P1-001`) et job `GenererFichierProcesVerbal` (PDF) (R51)
- [x] Action `PublierResultats` : après génération du procès-verbal ; les lauréates publiées sont reprises en phase suivante (R52, R54)
- [x] Femme modèle du Trésor (`Edition::femmeModele()`) et palmarès de l'édition (`EtablirPalmares`) (R53)
- [x] Tests des règles R44, R47 à R55
- [x] Procès-verbal en PDF (dompdf) aux en-tête et bas de page de la DGTCP, avec QR code de vérification ; affiché dans le navigateur ou téléchargé ; génération relançable
- [x] Écrans : fermeture manuelle si l'heure est passée, dépouillement, résultats provisoires réservés au comité et à l'observateur, départage des ex-æquo, prolongation, validation, publication, procès-verbal, palmarès pour tous
- [x] Journalisation de la validation et de la publication (R56, module 08)
- [x] Notification des lauréates à la publication : qualification pour la phase suivante en phases 1 et 2 (R35, R52), proclamation de la Femme modèle du Trésor en finale (R53) ; e-mail (file « mails ») et bandeau sur l'accueil

## Acteurs

Comité d'organisation (dépouillement, validation, publication, qualification) ; électeur et observateur (consultation).

## Entités du MCD

CANDIDATE (résultat), PROCES_VERBAL, BULLETIN

## Maquette

- `resources/views/maquettes/resultats.blade.php`, consultable à l'adresse `/maquettes/resultats`

## Écrans

- `/scrutins/{scrutin}` : dépouillement, départage, prolongation, validation, publication, procès-verbal (comité)
- `/palmares` : lauréates publiées et Femme modèle du Trésor (tous)
- `/verification/…` : page publique ouverte par le QR code des PDF

## Règles de gestion

Règles R47 à R55, extraites de [regles-de-gestion.md](../regles-de-gestion.md) :

### Dépouillement et résultats

- **R47** · À la clôture, le système calcule pour chaque candidate le nombre de voix, le pourcentage des suffrages exprimés et le rang, ainsi que le nombre d’inscrits, de votants, de bulletins blancs et le taux de participation.
- **R48** · Est lauréate du scrutin la candidate ayant obtenu le plus grand nombre de voix.
- **R49** · En cas d’égalité en tête, les ex-æquo sont départagées selon une modalité restant à préciser par le métier (voir les questions à éclaircir).
- **R50** · Un quorum de participation peut être fixé par scrutin ; s’il n’est pas atteint, le comité peut prolonger le scrutin.
- **R51** · Les résultats sont validés par le comité d’organisation ; un procès-verbal numéroté est généré pour chaque scrutin puis les résultats sont publiés.
- **R52** · Après publication, les lauréates sont qualifiées automatiquement pour la phase suivante (une qualifiée par scrutin par défaut, nombre paramétrable).
- **R53** · La lauréate de la finale est proclamée « Femme modèle du Trésor » de l’édition.
- **R54** · Les résultats d’une phase deviennent définitifs dès leur validation et leur publication par le comité d’organisation.
- **R55** · Les résultats publiés et les procès-verbaux sont archivés et ne peuvent plus être modifiés.
