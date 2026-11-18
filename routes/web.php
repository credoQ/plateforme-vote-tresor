<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/maquettes');

// Maquettes statiques de la charte (Kadoo 2.0, primaire bleu Trésor) : à retirer avant la mise en production.
Route::prefix('maquettes')->name('maquettes.')->group(function () {
    Route::view('/', 'maquettes.index')->name('index');
    Route::get('/{page}', fn (string $page) => view("maquettes.$page"))
        ->whereIn('page', ['connexion', 'tableau-de-bord', 'vote', 'candidates', 'resultats', 'tokens'])
        ->name('page');
});
