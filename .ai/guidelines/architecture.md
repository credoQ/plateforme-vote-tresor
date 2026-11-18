# Architecture et pile technique

## Pile

- Monolithe Laravel 13 (PHP 8.5), base PostgreSQL, interface Blade avec Tailwind CSS 4 et Vite 8, icônes Lucide.
- Le front reste dans le monolithe : vues Blade et composants anonymes (`resources/views/components`). N'ajoutez pas de framework JavaScript (Vue, React, Livewire, Inertia) sans accord.
- Les traitements longs (dépouillement, génération des procès-verbaux, envoi de notifications) passent par des jobs en file d'attente. L'ouverture et la clôture des scrutins passent par le planificateur.

## Organisation du code

- Monolithe découpé par domaine métier : Référentiel, Agents, Éditions, Scrutins, Vote, Résultats, Audit. Placez la logique métier dans des classes d'action ou de service (`app/Actions/<Domaine>/…`), pas dans les contrôleurs ni dans les vues.
- Valeurs fermées (statuts, origines, pôles) : enums PHP adossés à des chaînes, avec des clés en TitleCase (`StatutScrutin::Ouvert`).
- Paramètres d'édition encore à valider par le métier (vote blanc, quorum, nombre de qualifiées, départage) : colonnes ou configuration, jamais des constantes dans le code.

## Base de données

- Noms de tables et de colonnes en français, au pluriel et en snake_case, alignés sur le MCD : `editions`, `phases`, `scrutins`, `structures`, `types_structure`, `poles_electoraux`, `agents`, `affectations`, `inscriptions`, `candidates`, `bulletins`, `proces_verbaux`, `journal_audit`.
- Créez les migrations avec `php artisan make:migration`. Utilisez des clés étrangères contraintes et des index sur les colonnes de recherche (`matricule`, `scrutin_id`).

## Qualité

- Tests de fonctionnalité PHPUnit pour chaque règle de gestion implémentée, en citant la règle (`R40`) dans le nom ou le docblock du test.
- Formatez avec Pint (`vendor/bin/pint --dirty`) avant de terminer une tâche.
