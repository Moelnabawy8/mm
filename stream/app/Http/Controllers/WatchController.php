<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Services\StreamService;
use Illuminate\Http\Request;

class WatchController extends Controller
{
    public function __construct(protected StreamService $streamService) {}

    public function show(Request $request, string $hash)
    {
        $videoId = $this->streamService->decodeVideoHash($hash);
        if (!$videoId) {
            abort(404, 'Video not found');
        }

        $video = Video::findOrFail($videoId);

        if ($video->visibility === 'private') {
            abort(403, 'This video is private');
        }

        if (!$video->isReady()) {
            return view('watch-processing', compact('video'));
        }

        $this->streamService->logAccess($request, $video->id, false);

        $streamToken = $this->streamService->generateStreamToken($video->id);
        $streamUrl = route('stream.play', ['token' => $streamToken]);
        $tokenExpiry = config('securestream.stream_token_expiry', 10) * 60;

        $watermark = null;
        if (config('securestream.watermark.enabled')) {
            $watermark = [
                'text' => $request->ip(),
                'opacity' => config('securestream.watermark.opacity', 0.3),
                'reposition_interval' => config('securestream.watermark.reposition_interval', 30),
            ];
        }

        return view('watch', compact('video', 'streamUrl', 'tokenExpiry', 'watermark', 'hash'));
    }
}
