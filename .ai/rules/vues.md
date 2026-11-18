---
paths:
  - 'resources/views/**'
  - 'resources/css/**'
---

# Vues et styles

## Gabarit et composants

- Partez du gabarit `x-layouts.maquette` et des maquettes de `resources/views/maquettes/`, qui fixent la structure d'écran :
  - sidebar de 264 px ;
  - en-tête de 64 px qui affiche l'affectation ;
  - contenu à 28 px des bords.
- Réutilisez les classes de `resources/css/design/composants.css`.
- N'ajoutez ni couleur en dur, ni `style` inline pour une couleur, ni police externe.

## Textes et accessibilité

- Les textes sont en français.
- Les boutons d'action sont libellés par le verbe métier (« Désactiver la candidate », « Confirmer mon vote »), jamais « Oui » ou « OK ».
- Chaque modale de confirmation nomme l'objet concerné et dit ce qui va se passer.
- Pas de texte superflu à l'écran : ni sous-titre qui répète le titre ou le fil d'Ariane, ni paragraphe d'explication, ni surtitre de rubrique, ni rappel de règle de gestion, ni nom de commande technique. Gardez seulement ce qui informe l'utilisateur sur l'objet affiché (statut, dates, périmètre) ou ce dont il a besoin pour agir (consigne d'un champ, erreur, conséquence d'une action dans sa modale).

## Après une modification

- Vérifiez l'écran en thème clair et en thème sombre.
- Lancez `npm run build` si la modification n'apparaît pas.
