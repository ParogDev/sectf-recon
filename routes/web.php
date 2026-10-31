<?php

use App\Http\Controllers\App\ActivityController;
use App\Http\Controllers\App\DirectoryController;
use App\Http\Controllers\App\DossierController;
use App\Http\Controllers\App\DossierExportController;
use App\Http\Controllers\App\FindingController;
use App\Http\Controllers\App\FlagController;
use App\Http\Controllers\App\PretextController;
use App\Http\Controllers\App\SearchController;
use App\Http\Controllers\App\SessionController;
use App\Http\Controllers\App\TargetController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // The React dashboard; it routes client-side under /dashboard.
    Route::view('/dashboard/{path?}', 'spa')->where('path', '.*')->name('dashboard');

    // The Phase 1 dossier as a standalone print-ready document (Save as PDF to submit).
    Route::get('/dossier/{target}', [DossierController::class, 'html'])->whereNumber('target')->name('dossier');

    // Its JSON API. In web.php on purpose: session cookie + CSRF, same origin
    // as the SPA. bootstrap/app.php renders errors as JSON for api/* paths.
    Route::prefix('api/app')->name('app.')->group(function () {
        Route::get('session', [SessionController::class, 'show'])->name('session');

        Route::prefix('targets/{target}')->whereNumber('target')->name('targets.')->group(function () {
            Route::get('/', [TargetController::class, 'show'])->name('show');
            Route::post('select', [TargetController::class, 'select'])->name('select');
            Route::get('findings', [FindingController::class, 'index'])->name('findings');
            Route::get('activity', [ActivityController::class, 'index'])->name('activity');
            Route::get('people', [DirectoryController::class, 'people'])->name('people');
            Route::get('phones', [DirectoryController::class, 'phones'])->name('phones');
            Route::post('phones/{phone}/ring-verified', [DirectoryController::class, 'verifyPhone'])
                ->whereNumber('phone')->name('phones.ring-verified');
            Route::get('pretexts', [PretextController::class, 'index'])->name('pretexts');
            Route::get('searches', [SearchController::class, 'index'])->name('searches');
            Route::get('dossier', [DossierController::class, 'show'])->name('dossier');
            Route::post('dossier/export', [DossierExportController::class, 'store'])->name('dossier.export');
            Route::get('dossier/files/{name}', [DossierExportController::class, 'file'])
                ->where('name', '[A-Za-z0-9._-]+')->name('dossier.file');
            Route::patch('flags/{flag}', [FlagController::class, 'update'])
                ->where('flag', '\d{2}-\d{2}')->name('flags.update');
        });
    });
});

require __DIR__.'/settings.php';
