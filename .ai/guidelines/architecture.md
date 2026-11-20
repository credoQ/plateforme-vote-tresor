# Architecture et pile technique

## Pile

- Monolithe Laravel 13 (PHP 8.5), base PostgreSQL, interface Blade avec Tailwind CSS 4 et Vite 8, Livewire 4 (qui fournit Alpine.js), icônes Lucide.
- Le front reste dans le monolithe : vues Blade, composants anonymes (`resources/views/components`) et composants Livewire (`app/Livewire/<Domaine>/…`, vues dans `resources/views/livewire`). N'ajoutez pas d'autre framework JavaScript (Vue, React, Inertia, Turbo) sans accord.
- Livewire sert aux écrans qui gagnent à être interactifs (listes filtrées, tableau de bord). La logique métier reste dans les actions ; un composant ne fait que lire, filtrer et appeler une action. Les pages de vote (bulletin, confirmation) restent en Blade simple, sans Livewire : le choix ne transite que dans la requête de vote.
- N'importez jamais Alpine.js vous-même : Livewire le fournit. Les composants Alpine s'enregistrent sur l'événement `alpine:init` (`resources/js/app.js`), et le code lancé au chargement d'une page s'exécute sur `livewire:navigated`.
- Performance Livewire : `wire:model` sans `.live` dans les formulaires (rien n'est envoyé avant l'enregistrement) ; `.live.debounce.400ms` pour une recherche, `.live` pour une liste de filtres ; `#[Computed]` pour toute lecture ; état d'interface (menus, fenêtres) en Alpine, sans aller-retour ; îlots (`@island`) pour une zone rafraîchie seule (un îlot ne lit que des propriétés et des `$this->…`, et ne se place pas dans un `@if`).
- Pas de `wire:poll` sur un écran ouvert par tous les électeurs : rafraîchissez une fois à l'échéance connue. Un `wire:poll` de la console reste conditionnel ou limité à un îlot.
- Cache : pilote `file` en production. `Cache::memo()->remember()` pour une donnée relue souvent (édition en cours, comptages du tableau de bord), oubliée à l'écriture du modèle ; ne mettez en cache que des tableaux ou des scalaires (Laravel 13 ne restaure pas d'objets).
- Les traitements longs (dépouillement, génération des procès-verbaux, envoi de notifications) passent par des jobs en file d'attente. L'ouverture et la clôture des scrutins passent par le planificateur.

## Organisation du code

- Monolithe découpé par domaine métier : Référentiel, Agents, Éditions, Scrutins, Vote, Résultats, Audit. Placez la logique métier dans des classes d'action ou de service (`app/Actions/<Domaine>/…`), pas dans les contrôleurs ni dans les vues.
- Valeurs fermées (statuts, origines, pôles) : enums PHP adossés à des chaînes, avec des clés en TitleCase (`StatutScrutin::Ouvert`).
- Toute opération qui écrit plusieurs lignes ou tables (y compris l'entrée du journal d'audit) se fait dans `DB::transaction()` : tout est enregistré, ou rien. Une écriture conditionnée par un statut (ouverture, arrêt, désistement, planification) relit la ligne avec `lockForUpdate()` dans la transaction avant d'écrire.
- Paramètres d'édition encore à valider par le métier (vote blanc, quorum, nombre de qualifiées, départage) : colonnes ou configuration, jamais des constantes dans le code.

## Base de données

- Noms de tables et de colonnes en français, au pluriel et en snake_case, alignés sur le MCD : `editions`, `phases`, `scrutins`, `structures`, `types_structure`, `poles_electoraux`, `agents`, `affectations`, `inscriptions`, `candidates`, `bulletins`, `proces_verbaux`, `journal_audit`.
- Créez les migrations avec `php artisan make:migration`. Utilisez des clés étrangères contraintes et des index sur les colonnes de recherche (`matricule`, `scrutin_id`).

## Qualité

- Tests de fonctionnalité PHPUnit pour chaque règle de gestion implémentée, en citant la règle (`R40`) dans le nom ou le docblock du test.
- Formatez avec Pint (`vendor/bin/pint --dirty`) avant de terminer une tâche.
