# Dépouillement et résultats

**Module de dépouillement et de résultats**

## Objectif

Ce module calcule les résultats à la clôture du scrutin et organise la qualification des lauréates.

## Fonctionnalités

- [ ] Le décompte automatique des voix, le calcul des pourcentages, du rang et du taux de participation.
- [ ] La détection des égalités et leur traitement selon la modalité de départage qui sera retenue.
- [ ] La validation des résultats par le comité, la génération du procès-verbal et la publication.
- [ ] La qualification automatique des lauréates vers la phase suivante.
- [ ] La proclamation de la Femme modèle du Trésor et l’édition du palmarès (lauréates départementales, de structure et de pôle).

## Acteurs

Comité d'organisation (dépouillement, validation, publication, qualification) ; électeur et observateur (consultation).

## Entités du MCD

CANDIDATE (résultat), PROCES_VERBAL, BULLETIN

## Maquette

- `resources/views/maquettes/resultats.blade.php`, consultable à l'adresse `/maquettes/resultats`

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
