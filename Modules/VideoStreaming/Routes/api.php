<?php

use Illuminate\Support\Facades\Route;
use Modules\VideoStreaming\Http\Controllers\VideoStreamingController;
use Modules\VideoStreaming\Http\Controllers\VideoStreamController;

/*
|--------------------------------------------------------------------------
| Video Streaming Module API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['api'])->group(function () {
    
    // Public video info (limited)
    Route::get('/info/{uuid}', function ($uuid) {
        $video = \Modules\VideoStreaming\Entities\Video::where('uuid', $uuid)
            ->where('status', 'ready')
            ->where('is_public', true)
            ->first();

        if (!$video) {
            return response()->json(['error' => 'Video not found'], 404);
        }

        return response()->json([
            'uuid' => $video->uuid,
            'title' => $video->title,
            'duration' => $video->duration,
            'thumbnail' => $video->thumbnail_path ? asset($video->thumbnail_path) : null,
            'qualities' => $video->qualities->pluck('quality'),
        ]);
    })->name('api.video.info');

    // Generate embed token (for authorized integrations)
    Route::post('/token/{uuid}', function (\Illuminate\Http\Request $request, $uuid) {
        $video = \Modules\VideoStreaming\Entities\Video::where('uuid', $uuid)
            ->where('status', 'ready')
            ->firstOrFail();

        // Validate API key if required
        $apiKey = $request->header('X-API-Key');
        if (config('videostreaming.api_key') && $apiKey !== config('videostreaming.api_key')) {
            return response()->json(['error' => 'Invalid API key'], 401);
        }

        $streamingService = app(\Modules\VideoStreaming\Services\VideoStreamingService::class);
        $token = $streamingService->generateAccessToken(
            $video,
            $request->input('user_id'),
            $request->input('domain')
        );

        return response()->json([
            'token' => $token->token,
            'expires_at' => $token->expires_at->toISOString(),
            'embed_url' => route('video.embed', ['uuid' => $uuid]) . '?token=' . $token->token,
        ]);
    })->name('api.video.token');

    // Authenticated API routes
    Route::middleware(['auth:sanctum'])->group(function () {
        
        // List user's videos
        Route::get('/videos', function (\Illuminate\Http\Request $request) {
            $videos = \Modules\VideoStreaming\Entities\Video::where('user_id', auth()->id())
                ->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 15));

            return response()->json($videos);
        })->name('api.video.list');

        // Get video details
        Route::get('/videos/{uuid}', function ($uuid) {
            $video = \Modules\VideoStreaming\Entities\Video::where('uuid', $uuid)
                ->where('user_id', auth()->id())
                ->with(['qualities', 'embedSettings'])
                ->firstOrFail();

            return response()->json($video);
        })->name('api.video.show');

        // Upload video via API
        Route::post('/videos', [VideoStreamingController::class, 'store'])
            ->name('api.video.upload');

        // Update video
        Route::put('/videos/{uuid}', [VideoStreamingController::class, 'update'])
            ->name('api.video.update');

        // Delete video
        Route::delete('/videos/{uuid}', [VideoStreamingController::class, 'destroy'])
            ->name('api.video.delete');

        // Get video status
        Route::get('/videos/{uuid}/status', [VideoStreamingController::class, 'status'])
            ->name('api.video.status');

        // Get video analytics
        Route::get('/videos/{uuid}/analytics', [VideoStreamingController::class, 'analytics'])
            ->name('api.video.analytics');
    });
});
