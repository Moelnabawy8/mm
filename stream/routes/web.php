<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\BulkController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DomainController;
use App\Http\Controllers\Admin\PresetController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VideoController;
use App\Http\Controllers\Admin\VideoEditController;
use App\Http\Controllers\EmbedController;
use App\Http\Controllers\StreamController;
use App\Http\Controllers\WatchController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// Auth
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Admin Dashboard
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Videos
    Route::resource('videos', VideoController::class);
    Route::post('videos/{video}/upload-chunk', [VideoController::class, 'uploadChunk'])->name('videos.upload-chunk')->middleware('throttle:uploads');
    Route::get('videos/{video}/status', [VideoController::class, 'status'])->name('videos.status');
    Route::post('videos/{video}/domains', [VideoController::class, 'addDomain'])->name('videos.domains.store');
    Route::delete('videos/{video}/domains/{domainId}', [VideoController::class, 'removeDomain'])->name('videos.domains.destroy');
    Route::post('videos/{video}/freeze', [VideoController::class, 'freeze'])->name('videos.freeze');
    Route::post('videos/{video}/unfreeze', [VideoController::class, 'unfreeze'])->name('videos.unfreeze');
    Route::post('videos/{video}/security', [VideoController::class, 'updateSecurity'])->name('videos.security.update');
    Route::post('videos/{video}/watermark', [VideoController::class, 'updateWatermark'])->name('videos.watermark.update');
    Route::post('videos/{video}/apply-preset', [VideoController::class, 'applyPreset'])->name('videos.apply-preset');

    // Video Editing (FFmpeg)
    Route::post('videos/{video}/edit/trim', [VideoEditController::class, 'trim'])->name('videos.edit.trim');
    Route::post('videos/{video}/edit/regenerate-hls', [VideoEditController::class, 'regenerateHls'])->name('videos.edit.regenerate-hls');
    Route::post('videos/{video}/edit/regenerate-thumbnail', [VideoEditController::class, 'regenerateThumbnail'])->name('videos.edit.regenerate-thumbnail');
    Route::post('videos/{video}/edit/extract-frame', [VideoEditController::class, 'extractFrame'])->name('videos.edit.extract-frame');
    Route::post('videos/{video}/edit/change-bitrate', [VideoEditController::class, 'changeBitrate'])->name('videos.edit.change-bitrate');
    Route::post('videos/{video}/edit/replace-source', [VideoEditController::class, 'replaceSource'])->name('videos.edit.replace-source');

    // Sessions
    Route::get('sessions', [SessionController::class, 'index'])->name('sessions.index');
    Route::post('sessions/{session}/terminate', [SessionController::class, 'terminate'])->name('sessions.terminate');
    Route::post('sessions/block-ip', [SessionController::class, 'blockIp'])->name('sessions.block-ip');
    Route::post('sessions/block-fingerprint', [SessionController::class, 'blockFingerprint'])->name('sessions.block-fingerprint');
    Route::delete('blocked-ips/{blockedIp}', [SessionController::class, 'unblockIp'])->name('sessions.unblock-ip');
    Route::delete('blocked-fingerprints/{blockedFingerprint}', [SessionController::class, 'unblockFingerprint'])->name('sessions.unblock-fingerprint');

    // Users
    Route::resource('users', UserController::class)->except(['show']);

    // Allowed Domains (global)
    Route::get('domains', [DomainController::class, 'index'])->name('domains.index');
    Route::post('domains', [DomainController::class, 'store'])->name('domains.store');
    Route::delete('domains/{domain}', [DomainController::class, 'destroy'])->name('domains.destroy');

    // Security Presets
    Route::resource('presets', PresetController::class)->except(['show']);

    // Bulk Actions
    Route::post('bulk/apply-preset', [BulkController::class, 'applyPreset'])->name('bulk.apply-preset');
    Route::post('bulk/disable', [BulkController::class, 'disable'])->name('bulk.disable');
    Route::post('bulk/enable', [BulkController::class, 'enable'])->name('bulk.enable');
    Route::post('bulk/delete', [BulkController::class, 'delete'])->name('bulk.delete');
    Route::post('bulk/watermark', [BulkController::class, 'updateWatermark'])->name('bulk.watermark');

    // Analytics
    Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('analytics/data', [AnalyticsController::class, 'data'])->name('analytics.data');

    // Settings
    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
});

// Streaming (signed & protected)
Route::get('/secure-stream/{token}', [StreamController::class, 'play'])
    ->name('stream.play')
    ->middleware(['prevent.hotlinking', 'throttle:streaming']);

Route::get('/hls-segment/{videoId}/{segment}', [StreamController::class, 'hlsSegment'])
    ->name('stream.hls-segment')
    ->middleware(['prevent.hotlinking', 'throttle:streaming']);

Route::get('/hls-key/{videoId}/{token}', [StreamController::class, 'hlsKey'])
    ->name('stream.hls-key')
    ->middleware(['prevent.hotlinking', 'throttle:streaming']);

// Embed
Route::get('/embed/{hash}', [EmbedController::class, 'show'])
    ->name('embed.show');

// Public Watch Page
Route::get('/watch/{hash}', [WatchController::class, 'show'])
    ->name('watch.show');
