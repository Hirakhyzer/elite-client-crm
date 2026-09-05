<?php

use App\Http\Controllers\Crm\Phase44\PopupPreviewController;
use App\Http\Middleware\Phase4CrmAccess;
use Illuminate\Support\Facades\Route;

Route::middleware([Phase4CrmAccess::class])
    ->prefix('phase4/popup-preview')
    ->name('crm.phase4_4.popup-preview.')
    ->group(function () {
        Route::get('/', [PopupPreviewController::class, 'index'])->name('index');
        Route::get('/{id}', [PopupPreviewController::class, 'show'])->whereNumber('id')->name('show');
    });
