<?php

use Illuminate\Support\Facades\Route;
use Modules\VideoStreaming\Http\Controllers\VideoStreamingController;
use Modules\VideoStreaming\Http\Controllers\VideoStreamController;
use Modules\VideoStreaming\Http\Controllers\VideoSettingsController;

/*
|--------------------------------------------------------------------------
| Video Streaming Module Web Routes
|--------------------------------------------------------------------------
*/

// Public embed route (no auth required, but domain lock applies)
Route::get('/embed/{uuid}', [VideoStreamController::class, 'embed'])
    ->name('video.embed')
    ->middleware(['web']);

// Stream routes (protected by token validation)
Route::prefix('stream')->middleware(['web'])->group(function () {
    // Master playlist
    Route::get('/{uuid}/master.m3u8', [VideoStreamController::class, 'masterPlaylist'])
        ->name('video.stream.master');
    
    // Quality specific playlist
    Route::get('/{uuid}/{quality}/playlist.m3u8', [VideoStreamController::class, 'qualityPlaylist'])
        ->name('video.stream.playlist');
    
    // Video segments
    Route::get('/{uuid}/{quality}/segment/{segment}.ts', [VideoStreamController::class, 'segment'])
        ->name('video.stream.segment')
        ->where('segment', '[0-9]+');
    
    // Encryption key
    Route::get('/{uuid}/key/{keyId}', [VideoStreamController::class, 'encryptionKey'])
        ->name('video.stream.key');
});

// Analytics route
Route::post('/video/{uuid}/analytics', [VideoStreamController::class, 'recordAnalytics'])
    ->name('video.analytics')
    ->middleware(['web']);

// Authenticated routes
Route::middleware(['auth', 'web', 'XSS'])->group(function () {
    
    // Video Management Dashboard
    Route::prefix('video-streaming')->group(function () {
        
        // Video CRUD
        Route::get('/', [VideoStreamingController::class, 'index'])
            ->name('videostreaming.index');
        
        Route::get('/create', [VideoStreamingController::class, 'create'])
            ->name('videostreaming.create');
        
        Route::post('/upload', [VideoStreamingController::class, 'store'])
            ->name('videostreaming.store');
        
        Route::get('/video/{uuid}', [VideoStreamingController::class, 'show'])
            ->name('videostreaming.show');
        
        Route::put('/video/{uuid}', [VideoStreamingController::class, 'update'])
            ->name('videostreaming.update');
        
        Route::delete('/video/{uuid}', [VideoStreamingController::class, 'destroy'])
            ->name('videostreaming.destroy');
        
        // Video utilities
        Route::get('/video/{uuid}/status', [VideoStreamingController::class, 'status'])
            ->name('videostreaming.status');
        
        Route::get('/video/{uuid}/embed-code', [VideoStreamingController::class, 'embedCode'])
            ->name('videostreaming.embed-code');
        
        Route::get('/video/{uuid}/analytics', [VideoStreamingController::class, 'analytics'])
            ->name('videostreaming.analytics');
        
        // Settings
        Route::get('/settings', [VideoSettingsController::class, 'index'])
            ->name('videostreaming.settings');
        
        Route::post('/settings', [VideoSettingsController::class, 'update'])
            ->name('videostreaming.settings.update');
        
        Route::post('/settings/test-google-drive', [VideoSettingsController::class, 'testGoogleDrive'])
            ->name('videostreaming.settings.test-drive');
        
        Route::post('/settings/test-ffmpeg', [VideoSettingsController::class, 'testFfmpeg'])
            ->name('videostreaming.settings.test-ffmpeg');
    });
});
