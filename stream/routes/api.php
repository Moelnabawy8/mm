<?php

use App\Http\Controllers\EmbedController;
use App\Http\Controllers\StreamController;
use Illuminate\Support\Facades\Route;

Route::post('/track-access', [StreamController::class, 'trackAccess'])->name('api.track-access');
Route::post('/embed/refresh-token', [EmbedController::class, 'refreshToken'])->name('api.embed.refresh-token');
