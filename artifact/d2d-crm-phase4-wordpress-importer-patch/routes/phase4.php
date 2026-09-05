<?php

use App\Http\Controllers\Crm\Phase4\GuidebookResourceController;
use App\Http\Controllers\Crm\Phase4\WordPressImporterController;
use App\Http\Middleware\Phase4CrmAccess;
use Illuminate\Support\Facades\Route;

Route::middleware([Phase4CrmAccess::class])
    ->prefix('phase4')
    ->name('crm.phase4.')
    ->group(function () {
        Route::get('/wordpress', [WordPressImporterController::class, 'index'])->name('wordpress.index');
        Route::post('/wordpress/upload', [WordPressImporterController::class, 'upload'])->name('wordpress.upload');
        Route::post('/wordpress/dry-run', [WordPressImporterController::class, 'dryRun'])->name('wordpress.dry-run');
        Route::post('/wordpress/import', [WordPressImporterController::class, 'import'])->name('wordpress.import');
        Route::delete('/wordpress/source', [WordPressImporterController::class, 'clear'])->name('wordpress.clear');

        Route::get('/guidebooks', [GuidebookResourceController::class, 'index'])->name('guidebooks.index');
        Route::get('/guidebooks/create', [GuidebookResourceController::class, 'create'])->name('guidebooks.create');
        Route::post('/guidebooks', [GuidebookResourceController::class, 'store'])->name('guidebooks.store');
        Route::get('/guidebooks/{resource}', [GuidebookResourceController::class, 'show'])->name('guidebooks.show');
        Route::get('/guidebooks/{resource}/edit', [GuidebookResourceController::class, 'edit'])->name('guidebooks.edit');
        Route::put('/guidebooks/{resource}', [GuidebookResourceController::class, 'update'])->name('guidebooks.update');
        Route::delete('/guidebooks/{resource}', [GuidebookResourceController::class, 'archive'])->name('guidebooks.archive');
        Route::post('/guidebooks/{resource}/versions', [GuidebookResourceController::class, 'addVersion'])->name('guidebooks.versions.store');
        Route::post('/guidebooks/{resource}/versions/{version}/current', [GuidebookResourceController::class, 'makeCurrent'])->name('guidebooks.versions.current');
        Route::get('/guidebooks/{resource}/versions/{version}/download', [GuidebookResourceController::class, 'download'])->name('guidebooks.versions.download');
    });
