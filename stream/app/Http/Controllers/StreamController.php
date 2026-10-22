<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Services\HlsEncryptionService;
use App\Services\Storage\LocalStorageService;
use App\Services\Storage\StorageManager;
use App\Services\StreamService;
use Illuminate\Http\Request;

class StreamController extends Controller
{
    public function __construct(
        protected StreamService $streamService,
        protected StorageManager $storageManager,
        protected HlsEncryptionService $hlsService
    ) {}

    public function play(Request $request, string $token)
    {
        $payload = $this->streamService->validateStreamToken($token);
        if (!$payload) {
            abort(403, 'Invalid or expired stream token');
        }

        $video = Video::findOrFail($payload['video_id']);

        if (!$video->isReady()) {
            abort(404, 'Video is not available');
        }

        $this->streamService->logAccess($request, $video->id, false);

        return $this->streamHlsPlaylist($video);
    }

    public function hlsSegment(Request $request, string $videoId, string $segment)
    {
        $token = $request->query('token');
        if (!$token) {
            abort(403, 'Token required');
        }

        $payload = $this->streamService->validateStreamToken($token);
        if (!$payload || $payload['video_id'] !== $videoId) {
            abort(403, 'Invalid token');
        }

        $video = Video::findOrFail($videoId);
        $segmentPath = 'videos/' . $videoId . '/hls/' . $segment;

        $stream = $this->storageManager->stream($segmentPath, $video->storage_driver);
        if (!$stream) {
            abort(404, 'Segment not found');
        }

        $headers = array_merge($this->streamService->getSecureStreamHeaders(), [
            'Content-Type' => 'video/MP2T',
        ]);

        if (is_resource($stream)) {
            return response()->stream(function () use ($stream) {
                fpassthru($stream);
                fclose($stream);
            }, 200, $headers);
        }

        return response($stream, 200, $headers);
    }

    public function hlsKey(Request $request, string $videoId, string $token)
    {
        $payload = $this->streamService->validateHlsKeyToken($token);
        if (!$payload || $payload['video_id'] !== $videoId) {
            abort(403, 'Invalid or expired key token');
        }

        if (!$this->streamService->verifyDomain($request, $videoId)) {
            $this->streamService->logAccess($request, $videoId, true);
            abort(403);
        }

        $key = $this->hlsService->getEncryptionKey($videoId);
        if (!$key) {
            abort(404, 'Key not found');
        }

        $headers = array_merge($this->streamService->getSecureStreamHeaders(), [
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => strlen($key),
        ]);

        return response($key, 200, $headers);
    }

    public function trackAccess(Request $request)
    {
        $validated = $request->validate([
            'video_id' => 'required|exists:videos,id',
        ]);

        $this->streamService->logAccess($request, $validated['video_id'], false);

        return response()->json(['success' => true]);
    }

    protected function streamHlsPlaylist(Video $video): \Illuminate\Http\Response
    {
        $playlistPath = 'videos/' . $video->id . '/hls/playlist.m3u8';

        if ($video->storage_driver === 'local') {
            $localService = new LocalStorageService();
            $fullPath = $localService->getFullPath($playlistPath);

            if (!file_exists($fullPath)) {
                abort(404, 'Playlist not found');
            }

            $content = file_get_contents($fullPath);
        } else {
            $tempPath = $this->storageManager->download($playlistPath, $video->storage_driver);
            if (!$tempPath) {
                abort(404, 'Playlist not found');
            }
            $content = file_get_contents($tempPath);
            @unlink($tempPath);
        }

        $streamToken = $this->streamService->generateStreamToken($video->id);
        $keyToken = $this->streamService->generateHlsKeyToken($video->id);

        $content = preg_replace(
            '/#EXT-X-KEY:METHOD=AES-128,URI="[^"]*"/',
            '#EXT-X-KEY:METHOD=AES-128,URI="' . route('stream.hls-key', ['videoId' => $video->id, 'token' => $keyToken]) . '"',
            $content
        );

        $content = preg_replace_callback(
            '/(segment_\d+\.ts)/',
            function ($matches) use ($video, $streamToken) {
                return route('stream.hls-segment', [
                    'videoId' => $video->id,
                    'segment' => $matches[1],
                ]) . '?token=' . $streamToken;
            },
            $content
        );

        $headers = array_merge($this->streamService->getSecureStreamHeaders(), [
            'Content-Type' => 'application/vnd.apple.mpegurl',
        ]);

        return response($content, 200, $headers);
    }
}
