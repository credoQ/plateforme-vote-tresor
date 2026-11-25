<?php

use App\Http\Controllers\Acces\ConnexionController;
use App\Http\Controllers\Acces\UtilisateurController;
use App\Http\Controllers\Agents\AgentController;
use App\Http\Controllers\Editions\EditionController;
use App\Http\Controllers\Editions\FemmeModeleController;
use App\Http\Controllers\Editions\PhaseController;
use App\Http\Controllers\EspaceController;
use App\Http\Controllers\RapportCspController;
use App\Http\Controllers\Referentiel\StructureController;
use App\Http\Controllers\Resultats\ResultatController;
use App\Http\Controllers\Scrutins\NominationController;
use App\Http\Controllers\Scrutins\ScrutinController;
use App\Http\Controllers\Suivi\ExportController;
use App\Http\Controllers\Suivi\JournalAuditController;
use App\Http\Controllers\Suivi\TableauDeBordController;
use App\Http\Controllers\Suivi\TacheEchoueeController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\Vote\CandidatureController;
use App\Http\Controllers\Vote\VoteController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/espace');

// Vérification publique des documents PDF par leur QR code (liens signés).
Route::middleware(['signed', 'throttle:verification'])->prefix('verification')->name('verification.')->group(function () {
    Route::get('/proces-verbal/{numero}', [VerificationController::class, 'procesVerbal'])->name('proces-verbal');
    Route::get('/accuse-de-vote/{numero}', [VerificationController::class, 'accuseDeVote'])->name('accuse-de-vote');
    Route::get('/export', [VerificationController::class, 'export'])->name('export');
});

// Rapports de violation de la politique de sécurité du contenu, envoyés par les navigateurs.
Route::post('/csp-rapport', RapportCspController::class)->middleware('throttle:csp-rapport')->name('csp.rapport');

// Connexion sans mot de passe : matricule, puis code reçu par e-mail (R42).
Route::middleware('guest')->group(function () {
    Route::get('/connexion', [ConnexionController::class, 'create'])->name('login');
});

Route::middleware('auth')->group(function () {
    Route::post('/deconnexion', [ConnexionController::class, 'destroy'])->name('logout');

    Route::get('/espace', EspaceController::class)->name('espace');

    Route::resource('agents', AgentController::class)->except(['destroy', 'store', 'update']);
    Route::post('/agents/{agent}/affecter', [AgentController::class, 'affecter'])->name('agents.affecter');
    Route::post('/agents/{agent}/terminer', [AgentController::class, 'terminer'])->name('agents.terminer');

    Route::get('/structures/organisation', [StructureController::class, 'organisation'])->name('structures.organisation');
    Route::resource('structures', StructureController::class)->except(['destroy', 'store', 'update']);
    Route::post('/structures/{structure}/desactiver', [StructureController::class, 'desactiver'])->name('structures.desactiver');
    Route::post('/structures/{structure}/reactiver', [StructureController::class, 'reactiver'])->name('structures.reactiver');

    Route::middleware('can:voter')->group(function () {
        Route::get('/vote', [VoteController::class, 'show'])->name('vote');
        Route::post('/vote', [VoteController::class, 'store'])->middleware('throttle:vote')->name('vote.store');
        Route::get('/vote/accuse/{numero}', [VoteController::class, 'accuse'])->name('vote.accuse');
    });
    Route::post('/candidatures/{candidate}/desister', [CandidatureController::class, 'desister'])->name('candidatures.desister');
    Route::post('/candidatures/{candidate}/reactiver', [CandidatureController::class, 'reactiver'])->name('candidatures.reactiver');
    Route::get('/candidates/{candidate}/photo', [CandidatureController::class, 'photo'])->name('candidates.photo');

    Route::get('/tableau-de-bord', TableauDeBordController::class)->name('tableau-de-bord');
    Route::get('/scrutins/{scrutin}', [ScrutinController::class, 'show'])->name('scrutins.show');
    Route::get('/scrutins/{scrutin}/proces-verbal', [ResultatController::class, 'procesVerbal'])->name('resultats.proces-verbal');
    Route::get('/palmares/{edition?}', [ResultatController::class, 'palmares'])->name('palmares');
    Route::get('/journal', JournalAuditController::class)->middleware('can:observer')->name('journal');
    Route::get('/scrutins/{scrutin}/liste-electorale', [ExportController::class, 'listeElectorale'])->name('exports.liste-electorale');
    Route::get('/scrutins/{scrutin}/resultats', [ExportController::class, 'resultats'])->name('exports.resultats');

    Route::middleware('can:piloter')->group(function () {
        Route::resource('editions', EditionController::class)->except(['destroy', 'store', 'update']);
        Route::post('/editions/{edition}/lancer', [EditionController::class, 'lancer'])->name('editions.lancer');
        Route::put('/phases/{phase}', [PhaseController::class, 'update'])->name('phases.update');
        Route::post('/phases/{phase}/ouvrir', [PhaseController::class, 'ouvrir'])->name('phases.ouvrir');
        Route::post('/phases/{phase}/cloturer', [PhaseController::class, 'cloturer'])->name('phases.cloturer');
        Route::post('/phases/{phase}/arreter-listes', [PhaseController::class, 'arreterListes'])->name('phases.arreter-listes');
        Route::post('/phases/{phase}/planifier-scrutins', [PhaseController::class, 'planifierScrutins'])->name('phases.planifier-scrutins');
        Route::post('/phases/{phase}/scrutins', [ScrutinController::class, 'generer'])->name('phases.scrutins.generer');
        Route::post('/scrutins/{scrutin}/planifier', [ScrutinController::class, 'planifier'])->name('scrutins.planifier');
        Route::post('/scrutins/{scrutin}/rouvrir', [ScrutinController::class, 'rouvrir'])->name('scrutins.rouvrir');
        Route::get('/femmes-modeles', [FemmeModeleController::class, 'index'])->name('femmes-modeles.index');
        Route::get('/femmes-modeles/ajouter', [FemmeModeleController::class, 'create'])->name('femmes-modeles.create');
        Route::post('/femmes-modeles/{femmeModele}/supprimer', [FemmeModeleController::class, 'destroy'])->name('femmes-modeles.destroy');
        Route::post('/scrutins/{scrutin}/nominations', [NominationController::class, 'store'])->name('nominations.store');
        Route::post('/candidates/{candidate}/retirer-nomination', [NominationController::class, 'destroy'])->name('nominations.destroy');
        Route::post('/scrutins/{scrutin}/fermer', [ResultatController::class, 'fermer'])->name('scrutins.fermer');
        Route::post('/scrutins/{scrutin}/depouiller', [ResultatController::class, 'depouiller'])->name('resultats.depouiller');
        Route::post('/scrutins/{scrutin}/departager', [ResultatController::class, 'trancher'])->name('resultats.trancher');
        Route::post('/scrutins/{scrutin}/prolonger', [ResultatController::class, 'prolonger'])->name('resultats.prolonger');
        Route::post('/scrutins/{scrutin}/valider', [ResultatController::class, 'valider'])->name('resultats.valider');
        Route::post('/scrutins/{scrutin}/publier', [ResultatController::class, 'publier'])->name('resultats.publier');
        Route::post('/scrutins/{scrutin}/proces-verbal', [ResultatController::class, 'regenerer'])->name('resultats.regenerer');
    });

    Route::middleware('can:administrer')->group(function () {
        Route::resource('utilisateurs', UtilisateurController::class)
            ->only(['index', 'create', 'edit'])
            ->parameters(['utilisateurs' => 'utilisateur']);
        Route::post('/utilisateurs/{utilisateur}/lier-agent', [UtilisateurController::class, 'lierAgent'])->name('utilisateurs.lier-agent');

        Route::get('/taches-echouees', [TacheEchoueeController::class, 'index'])->name('taches-echouees.index');
        Route::post('/taches-echouees/relancer', [TacheEchoueeController::class, 'relancerTout'])->name('taches-echouees.relancer-tout');
        Route::post('/taches-echouees/{uuid}/relancer', [TacheEchoueeController::class, 'relancer'])->whereUuid('uuid')->name('taches-echouees.relancer');
        Route::post('/taches-echouees/{uuid}/supprimer', [TacheEchoueeController::class, 'supprimer'])->whereUuid('uuid')->name('taches-echouees.supprimer');
    });
});

// Maquettes statiques de la charte (Kadoo 2.0, primaire bleu Trésor) : à retirer avant la mise en production.
Route::prefix('maquettes')->name('maquettes.')->group(function () {
    Route::view('/', 'maquettes.index')->name('index');
    Route::get('/{page}', fn (string $page) => view("maquettes.$page"))
        ->whereIn('page', ['connexion', 'tableau-de-bord', 'vote', 'candidates', 'resultats', 'tokens'])
        ->name('page');
});
