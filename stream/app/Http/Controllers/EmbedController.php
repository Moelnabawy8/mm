<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Services\StreamService;
use Illuminate\Http\Request;

class EmbedController extends Controller
{
    public function __construct(protected StreamService $streamService) {}

    public function show(Request $request, string $hash)
    {
        $videoId = $this->streamService->decodeVideoHash($hash);
        if (!$videoId) {
            abort(404, 'Video not found');
        }

        $video = Video::findOrFail($videoId);

        if (!$this->streamService->verifyDomain($request, $video->id)) {
            $this->streamService->logAccess($request, $video->id, true);
            return view('embed.unauthorized');
        }

        if (!$video->isReady()) {
            abort(404, 'Video is not available');
        }

        if ($video->visibility === 'private') {
            return view('embed.unauthorized');
        }

        $this->streamService->logAccess($request, $video->id, false);

        $streamToken = $this->streamService->generateStreamToken($video->id);
        $streamUrl = route('stream.play', ['token' => $streamToken]);
        $tokenExpiry = config('securestream.stream_token_expiry', 10) * 60;

        $watermark = null;
        if (config('securestream.watermark.enabled')) {
            $referer = $request->header('Referer') ?? $request->header('Origin') ?? 'unknown';
            $watermark = [
                'text' => parse_url($referer, PHP_URL_HOST) ?? $request->ip(),
                'opacity' => config('securestream.watermark.opacity', 0.3),
                'reposition_interval' => config('securestream.watermark.reposition_interval', 30),
            ];
        }

        return view('embed.player', compact(
            'video',
            'streamUrl',
            'streamToken',
            'tokenExpiry',
            'watermark',
            'hash'
        ));
    }

    public function refreshToken(Request $request)
    {
        $validated = $request->validate([
            'video_hash' => 'required|string',
        ]);

        $videoId = $this->streamService->decodeVideoHash($validated['video_hash']);
        if (!$videoId) {
            return response()->json(['error' => 'Invalid video'], 403);
        }

        if (!$this->streamService->verifyDomain($request, $videoId)) {
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        $newToken = $this->streamService->generateStreamToken($videoId);
        $streamUrl = route('stream.play', ['token' => $newToken]);

        return response()->json([
            'stream_url' => $streamUrl,
            'token' => $newToken,
            'expires_in' => config('securestream.stream_token_expiry', 10) * 60,
        ]);
    }
}
