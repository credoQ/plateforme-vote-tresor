# Projet : vote de la Femme modèle du Trésor

## Contexte

- Plateforme de vote électronique de la Direction générale du Trésor et de la Comptabilité publique (DGTCP, Bénin). Chaque édition annuelle désigne la « Femme modèle du Trésor ».
- Références fonctionnelles, dans le dépôt :
  - `docs/fonctionnel/regles-de-gestion.md` : règles R1 à R57 ;
  - `docs/fonctionnel/modules/*.md` : fonctionnalités de chaque module, avec ses acteurs, ses entités et ses règles ;
  - le document technique Word (MCD, cas d'utilisation), hors du dépôt.
- En cas de doute sur une règle métier, lisez ces fichiers plutôt que de supposer. Citez le numéro de la règle (`R40`) dans le code et les tests concernés.
- Quand une fonctionnalité est livrée, cochez sa case dans le fichier du module.
- Les « Points à valider et questions à éclaircir » (fin de `regles-de-gestion.md`) ne sont pas tranchés : ne les codez pas en dur, rendez-les paramétrables ou demandez.

## Langue

- L'interface, les messages, les libellés de validation et les e-mails sont en français (`APP_LOCALE=fr`).
- Le vocabulaire métier reste en français dans le code (classes, tables, colonnes, routes) pour coller au MCD : `Edition`, `Phase`, `Scrutin`, `Candidate`, `Bulletin`, `ProcesVerbal`, `Structure`, `TypeStructure`, `PoleElectoral`, `Departement`, `Agent`. Les mots techniques Laravel (controller, request, policy, job) restent en anglais.

## Déroulement électoral

- Une édition comporte exactement trois phases, ouvertes dans l'ordre :
  1. Phase 1, primaires : 12 scrutins départementaux (TD et Trésoreries communales d'un département) et un scrutin par structure du pôle structures.
  2. Phase 2, demi-finales : un scrutin par pôle électoral (départemental, structures).
  3. Phase 3, finale : un scrutin, tous les agents de la DGTCP.
- Une phase ne s'ouvre que si tous les résultats de la phase précédente sont validés et publiés.
- Un agent vote au plus une fois par phase, dans la circonscription de son poste d'affectation. Les agents d'une structure centrale en poste dans une TD (antennes de la DIA, par exemple) votent avec leur département.
- Il n'y a pas de dépôt de candidature : en phase 1, toutes les agents de sexe féminin en service dans la circonscription à la date de gel sont candidates d'office. En phases 2 et 3, les candidates sont les lauréates qualifiées.
- Aucun scrutin n'est créé pour une structure sans agent femme en service : ses agents ne votent pas en phase 1.
- Une candidate peut être désactivée (désistement par elle-même, ou par le point focal ou le comité à sa demande) jusqu'à l'ouverture du scrutin. Chaque désactivation et chaque réactivation sont tracées.
- « En service » signifie : figurer dans la liste des agents avec une affectation en cours. Les champs `date_prise_service`, `telephone`, `email`, `fonction` et `statut` de l'agent sont informatifs : aucune règle ne doit s'appuyer dessus.
- Hors périmètre : la gestion des candidatures (dépôt, validation) et les réclamations. Ne pas les implémenter.

## Notifications

- Les notifications (annonces, codes de connexion) sont envoyées uniquement par e-mail. Pas de SMS ni de passerelle tierce.
- Tout envoi d'e-mail est asynchrone : chaque notification implémente `ShouldQueue` (jamais `notifyNow`, jamais `Mail::send` direct), avec ses tentatives et son délai de reprise. Files : `urgent` pour les codes de connexion (job chiffré avec `ShouldBeEncrypted`, abandonné après la validité du code), `mails` pour les annonces.
- En production, le processus de file traite les files par priorité : `php artisan queue:work --queue=urgent,mails,default`. Les échecs se relancent depuis l'écran « Tâches en échec ».
- L'adresse utilisée est celle du compte, sinon celle de la fiche agent ; un agent sans adresse voit les annonces sur la plateforme.

## Authentification

- Connexion sans mot de passe (R42) : matricule, puis code à usage unique envoyé par e-mail (`app/Actions/Acces/DemanderCodeConnexion.php`, `VerifierCodeConnexion.php`, `ConnexionController`). N'ajoutez ni mot de passe, ni paquet d'authentification (Fortify, Breeze…).
- L'adresse utilisée est `User::adresseDeConnexion()` : celle du compte, sinon celle de la fiche agent. Un compte sans adresse ne peut pas se connecter.
- Les connexions, échecs et déconnexions sont journalisés par les écouteurs des événements `Login`, `Failed` et `Logout` (`AppServiceProvider`).

## Profils

Administrateur, Comité d'organisation, Point focal (limité à sa structure), Électeur, Observateur (lecture seule). Contrôlez les droits avec des Policies et des Gates, jamais seulement dans les vues.
