<?php

use App\Http\Controllers\Phase5\PublicContentController;
use Illuminate\Support\Facades\Route;

Route::get('/blog', [PublicContentController::class, 'blog'])->name('phase5b.blog');
Route::get('/blog/{slug}', [PublicContentController::class, 'blogShow'])->name('phase5b.blog.show');

Route::get('/universities', [PublicContentController::class, 'universities'])->name('phase5b.universities');
Route::get('/universities/{slug}', [PublicContentController::class, 'universityShow'])->name('phase5b.universities.show');

Route::get('/opportunities', [PublicContentController::class, 'opportunities'])->name('phase5b.opportunities');
Route::get('/opportunities/{slug}', [PublicContentController::class, 'opportunityShow'])->name('phase5b.opportunities.show');

Route::get('/resources', [PublicContentController::class, 'resources'])->name('phase5b.resources');

Route::prefix('api/v1')->group(function () {
    Route::get('/home', [PublicContentController::class, 'apiHome']);
    Route::get('/blog', [PublicContentController::class, 'apiBlog']);
    Route::get('/blog/{slug}', [PublicContentController::class, 'apiBlogShow']);
    Route::get('/universities', [PublicContentController::class, 'apiUniversities']);
    Route::get('/opportunities', [PublicContentController::class, 'apiOpportunities']);
    Route::get('/guidebooks', [PublicContentController::class, 'apiGuidebooks']);
});

Route::get('/phase5-media/{path}', [PublicContentController::class, 'media'])->where('path', '.*');
