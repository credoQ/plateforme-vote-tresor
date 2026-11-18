<?php

use App\Http\Controllers\Acces\ConnexionController;
use App\Http\Controllers\Acces\MotDePasseController;
use App\Http\Controllers\Acces\ReinitialisationMotDePasseController;
use App\Http\Controllers\Acces\UtilisateurController;
use App\Http\Controllers\EspaceController;
use App\Http\Controllers\Suivi\ExportController;
use App\Http\Controllers\Suivi\JournalAuditController;
use App\Http\Controllers\Suivi\TableauDeBordController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/espace');

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [ConnexionController::class, 'create'])->name('connexion');
    Route::post('/connexion', [ConnexionController::class, 'store'])->name('connexion.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/deconnexion', [ConnexionController::class, 'destroy'])->name('deconnexion');
    Route::get('/mot-de-passe', [MotDePasseController::class, 'edit'])->name('mot-de-passe.edit');
    Route::put('/mot-de-passe', [MotDePasseController::class, 'update'])->name('mot-de-passe.update');

    Route::get('/espace', EspaceController::class)->name('espace');

    Route::get('/tableau-de-bord', TableauDeBordController::class)->name('tableau-de-bord');
    Route::get('/journal', JournalAuditController::class)->middleware('can:observer')->name('journal');
    Route::get('/scrutins/{scrutin}/liste-electorale.csv', [ExportController::class, 'listeElectorale'])->name('exports.liste-electorale');
    Route::get('/scrutins/{scrutin}/resultats.csv', [ExportController::class, 'resultats'])->name('exports.resultats');

    Route::middleware('can:administrer')->group(function () {
        Route::resource('utilisateurs', UtilisateurController::class)
            ->only(['index', 'create', 'store', 'edit', 'update'])
            ->parameters(['utilisateurs' => 'utilisateur']);
        Route::post('/utilisateurs/{utilisateur}/reinitialiser', ReinitialisationMotDePasseController::class)
            ->name('utilisateurs.reinitialiser');
    });
});

// Maquettes statiques de la charte (Kadoo 2.0, primaire bleu Trésor) : à retirer avant la mise en production.
Route::prefix('maquettes')->name('maquettes.')->group(function () {
    Route::view('/', 'maquettes.index')->name('index');
    Route::get('/{page}', fn (string $page) => view("maquettes.$page"))
        ->whereIn('page', ['connexion', 'tableau-de-bord', 'vote', 'candidates', 'resultats', 'tokens'])
        ->name('page');
});
