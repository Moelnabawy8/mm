<?php

use Illuminate\Support\Facades\Route;
use Modules\Guide\Http\Controllers\GuideController;

Route::middleware(['auth', 'web', 'XSS'])->prefix('guide')->group(function () {
    Route::get('/', [GuideController::class, 'index'])->name('guide.index');
    Route::get('/videos', [GuideController::class, 'videos'])->name('guide.videos');
    Route::get('/videos/{id}', [GuideController::class, 'showVideo'])->name('guide.videos.show');
    Route::get('/documentation', [GuideController::class, 'documentation'])->name('guide.documentation');
    Route::get('/documentation/{id}', [GuideController::class, 'showDocument'])->name('guide.documentation.show');
    Route::get('/category/{slug}', [GuideController::class, 'category'])->name('guide.category');
    Route::get('/search', [GuideController::class, 'search'])->name('guide.search');
});
