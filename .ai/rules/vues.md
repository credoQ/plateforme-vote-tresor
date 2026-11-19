---
paths:
  - 'resources/views/**'
  - 'resources/css/**'
---

# Vues et styles

## Gabarit et composants

- Les écrans connectés utilisent le gabarit `x-layouts.app`, les écrans hors connexion (connexion, vérification d'un document) `x-layouts.invite`. Les maquettes de `resources/views/maquettes/` (gabarit `x-layouts.maquette`) fixent la structure d'écran :
  - sidebar de 264 px ;
  - en-tête de 64 px qui affiche l'affectation ;
  - contenu à 28 px des bords.
- Réutilisez les classes de `resources/css/design/composants.css` et les composants `x-modale-confirmation` et `x-liens-export`.
- N'ajoutez ni couleur en dur, ni `style` inline pour une couleur, ni police externe.

## Pas de traitement serveur dans les vues

- Une vue affiche des données prêtes : pas de bloc `@php`, pas de requête, pas de calcul ni de filtrage métier, pas d'appel à `auth()` ou `app()`.
- Préparez les données dans le contrôleur, une méthode ou une relation du modèle (`Structure::estUnService()`, `Agent::affectationEnCours`), ou un view composer pour un gabarit ou un composant partagé (`NavigationComposer` pour `components.layouts.app`, enregistré dans `AppServiceProvider`).
- La couleur d'un badge de statut vient de l'enum (`$scrutin->statut->badge()`), pas d'un tableau dans la vue.
- Restent dans la vue : l'affichage conditionnel simple, les directives d'autorisation (`@can`), le formatage (`number_format`, `->format('d/m/Y')`), les messages de session et l'import d'un enum par `@use('App\Enums\…')` pour une comparaison de statut.

## Textes et accessibilité

- Les textes sont en français.
- Les boutons d'action sont libellés par le verbe métier (« Désactiver la candidate », « Confirmer mon vote »), jamais « Oui » ou « OK ».
- Chaque modale de confirmation nomme l'objet concerné et dit ce qui va se passer.
- Pas de texte superflu à l'écran : ni sous-titre qui répète le titre ou le fil d'Ariane, ni paragraphe d'explication, ni surtitre de rubrique, ni rappel de règle de gestion, ni nom de commande technique. Gardez seulement ce qui informe l'utilisateur sur l'objet affiché (statut, dates, périmètre) ou ce dont il a besoin pour agir (consigne d'un champ, erreur, conséquence d'une action dans sa modale).

## Documents PDF

- Tout PDF passe par le composant `x-pdf.document` (`resources/views/components/pdf/document.blade.php`) : en-tête aux logos du ministère, ISO 9001 et DGTCP avec la bande tricolore, et bas de page avec le QR code de vérification en ligne, la mention d'édition et le type de document.
- Le QR code porte un lien signé (`LienVerification`) vers la page publique de vérification ; il ne contient jamais de donnée personnelle.
- Les images d'en-tête sont dans `resources/images/pdf/` et intégrées en data URI.

## Après une modification

- Vérifiez l'écran en thème clair et en thème sombre.
- Lancez `npm run build` si la modification n'apparaît pas.
