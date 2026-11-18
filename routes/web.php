<?php

use App\Http\Controllers\Acces\ConnexionController;
use App\Http\Controllers\Acces\MotDePasseController;
use App\Http\Controllers\Acces\ReinitialisationMotDePasseController;
use App\Http\Controllers\Acces\UtilisateurController;
use App\Http\Controllers\Editions\EditionController;
use App\Http\Controllers\Editions\PhaseController;
use App\Http\Controllers\EspaceController;
use App\Http\Controllers\Scrutins\ScrutinController;
use App\Http\Controllers\Suivi\ExportController;
use App\Http\Controllers\Suivi\JournalAuditController;
use App\Http\Controllers\Suivi\TableauDeBordController;
use App\Http\Controllers\Vote\CandidatureController;
use App\Http\Controllers\Vote\VoteController;
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

    Route::middleware('can:voter')->group(function () {
        Route::get('/vote', [VoteController::class, 'show'])->name('vote');
        Route::post('/vote/confirmation', [VoteController::class, 'confirmer'])->name('vote.confirmer');
        Route::post('/vote', [VoteController::class, 'store'])->middleware('throttle:10,1')->name('vote.store');
    });
    Route::post('/candidatures/{candidate}/desister', [CandidatureController::class, 'desister'])->name('candidatures.desister');
    Route::post('/candidatures/{candidate}/reactiver', [CandidatureController::class, 'reactiver'])->name('candidatures.reactiver');

    Route::get('/tableau-de-bord', TableauDeBordController::class)->name('tableau-de-bord');
    Route::get('/scrutins/{scrutin}', [ScrutinController::class, 'show'])->name('scrutins.show');
    Route::get('/journal', JournalAuditController::class)->middleware('can:observer')->name('journal');
    Route::get('/scrutins/{scrutin}/liste-electorale.csv', [ExportController::class, 'listeElectorale'])->name('exports.liste-electorale');
    Route::get('/scrutins/{scrutin}/resultats.csv', [ExportController::class, 'resultats'])->name('exports.resultats');

    Route::middleware('can:piloter')->group(function () {
        Route::resource('editions', EditionController::class)->except(['destroy']);
        Route::post('/editions/{edition}/lancer', [EditionController::class, 'lancer'])->name('editions.lancer');
        Route::put('/phases/{phase}', [PhaseController::class, 'update'])->name('phases.update');
        Route::post('/phases/{phase}/ouvrir', [PhaseController::class, 'ouvrir'])->name('phases.ouvrir');
        Route::post('/phases/{phase}/cloturer', [PhaseController::class, 'cloturer'])->name('phases.cloturer');
        Route::post('/phases/{phase}/scrutins', [ScrutinController::class, 'generer'])->name('phases.scrutins.generer');
        Route::post('/scrutins/{scrutin}/planifier', [ScrutinController::class, 'planifier'])->name('scrutins.planifier');
        Route::post('/scrutins/{scrutin}/actualiser', [ScrutinController::class, 'actualiser'])->name('scrutins.actualiser');
        Route::post('/scrutins/{scrutin}/arreter', [ScrutinController::class, 'arreter'])->name('scrutins.arreter');
    });

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
