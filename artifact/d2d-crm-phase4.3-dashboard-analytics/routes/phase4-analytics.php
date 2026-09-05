<?php

use App\Http\Controllers\Crm\Phase4\AnalyticsController;
use App\Http\Middleware\Phase4CrmAccess;
use Illuminate\Support\Facades\Route;

Route::middleware([Phase4CrmAccess::class])
    ->get('/analytics', [AnalyticsController::class, 'index'])
    ->name('crm.analytics.index');
