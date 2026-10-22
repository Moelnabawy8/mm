<?php

namespace Modules\VideoStreaming\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\VideoStreaming\Entities\Video;
use Modules\VideoStreaming\Services\VideoStreamingService;
use Modules\VideoStreaming\Services\HLSEncryptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class VideoStreamController extends Controller
{
    protected VideoStreamingService $streamingService;
    protected HLSEncryptionService $encryptionService;

    public function __construct(
        VideoStreamingService $streamingService,
        HLSEncryptionService $encryptionService
    ) {
        $this->streamingService = $streamingService;
        $this->encryptionService = $encryptionService;
    }

    /**
     * Embed player page.
     */
    public function embed(string $uuid)
    {
        $video = Video::where('uuid', $uuid)
            ->where('status', 'ready')
            ->with(['embedSettings', 'qualities'])
            ->firstOrFail();

        // Check domain lock
        if ($video->domain_lock_enabled && config('videostreaming.security.enable_domain_lock')) {
            $referer = request()->header('Referer');
            if ($referer) {
                $refererDomain = parse_url($referer, PHP_URL_HOST);
                $allowedDomains = $video->allowed_domains ?? [];
                $configDomains = explode(',', config('videostreaming.security.allowed_domains', ''));
                $allAllowedDomains = array_filter(array_merge($allowedDomains, $configDomains));

                if (!empty($allAllowedDomains)) {
                    $isAllowed = false;
                    foreach ($allAllowedDomains as $domain) {
                        $domain = trim($domain);
                        if ($domain && (str_ends_with($refererDomain, $domain) || $refererDomain === $domain)) {
                            $isAllowed = true;
                            break;
                        }
                    }

                    if (!$isAllowed) {
                        abort(403, 'This video cannot be played on this domain.');
                    }
                }
            }
        }

        // Generate access token
        $accessToken = $this->streamingService->generateAccessToken(
            $video,
            auth()->id(),
            request()->header('Referer') ? parse_url(request()->header('Referer'), PHP_URL_HOST) : null
        );

        return view('videostreaming::player.embed', [
            'video' => $video,
            'token' => $accessToken->token,
            'config' => $video->embedSettings->player_config ?? [],
        ]);
    }

    /**
     * Get master playlist (m3u8).
     */
    public function masterPlaylist(Request $request, string $uuid): Response
    {
        $video = Video::where('uuid', $uuid)
            ->where('status', 'ready')
            ->firstOrFail();

        $token = $request->query('token');
        if (!$token) {
            abort(401, 'Access token required');
        }

        $accessToken = $this->streamingService->validateAccessToken($token, $video);
        if (!$accessToken) {
            abort(403, 'Invalid or expired token');
        }

        $playlist = $this->streamingService->getMasterPlaylist($video, $token);
        if (!$playlist) {
            abort(404, 'Playlist not found');
        }

        return response($playlist, 200, [
            'Content-Type' => 'application/vnd.apple.mpegurl',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * Get quality playlist (m3u8).
     */
    public function qualityPlaylist(Request $request, string $uuid, string $quality): Response
    {
        $video = Video::where('uuid', $uuid)
            ->where('status', 'ready')
            ->firstOrFail();

        $token = $request->query('token');
        if (!$token) {
            abort(401, 'Access token required');
        }

        $accessToken = $this->streamingService->validateAccessToken($token, $video);
        if (!$accessToken) {
            abort(403, 'Invalid or expired token');
        }

        $playlist = $this->streamingService->getQualityPlaylist($video, $quality, $token);
        if (!$playlist) {
            abort(404, 'Playlist not found');
        }

        return response($playlist, 200, [
            'Content-Type' => 'application/vnd.apple.mpegurl',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * Get video segment (.ts) - optimized.
     */
    public function segment(Request $request, string $uuid, string $quality, int $segment): Response
    {
        $video = Video::where('uuid', $uuid)
            ->where('status', 'ready')
            ->firstOrFail();

        $token = $request->query('token');
        if (!$token) {
            abort(401, 'Access token required');
        }

        $accessToken = $this->streamingService->validateAccessToken($token, $video);
        if (!$accessToken) {
            abort(403, 'Invalid or expired token');
        }

        $etag = md5("{$uuid}:{$quality}:{$segment}:{$video->updated_at}");

        if ($request->header('If-None-Match') === $etag) {
            return response('', 304);
        }

        $segmentContent = $this->streamingService->getSegment($video, $quality, $segment);
        if (!$segmentContent) {
            abort(404, 'Segment not found');
        }

        return response($segmentContent, 200, [
            'Content-Type' => 'video/mp2t',
            'Content-Length' => strlen($segmentContent),
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => $etag,
            'Accept-Ranges' => 'bytes',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
            'Access-Control-Allow-Headers' => 'Range, If-None-Match',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Get encryption key.
     */
    public function encryptionKey(Request $request, string $uuid, string $keyId): Response
    {
        $video = Video::where('uuid', $uuid)
            ->where('status', 'ready')
            ->firstOrFail();

        $token = $request->query('token');
        if (!$token) {
            Log::warning("Key request without token for video {$uuid}");
            abort(401, 'Access token required');
        }

        $accessToken = $this->streamingService->validateAccessToken($token, $video);
        if (!$accessToken) {
            Log::warning("Invalid token for key request: video {$uuid}, token {$token}");
            abort(403, 'Invalid or expired token');
        }

        // Additional security: verify signature if provided
        $signature = $request->query('signature');
        $expires = $request->query('expires');

        if ($signature && $expires) {
            if (!$this->encryptionService->verifySignature($uuid, $keyId, $token, (int)$expires, $signature)) {
                Log::warning("Invalid signature for key request: video {$uuid}");
                abort(403, 'Invalid signature');
            }
        }

        // Check referer domain
        $referer = $request->header('Referer');
        if (!$referer) {
            Log::warning("Key request without referer for video {$uuid}");
            // Don't abort, some browsers might not send referer
        } else {
            $refererDomain = parse_url($referer, PHP_URL_HOST);
            $ourDomain = parse_url(config('app.url'), PHP_URL_HOST);

            $allowedDomains = $video->allowed_domains ?? [];
            $configDomains = explode(',', config('videostreaming.security.allowed_domains', ''));
            $allAllowedDomains = array_filter(array_merge([$ourDomain], $allowedDomains, $configDomains));

            $isAllowed = false;
            foreach ($allAllowedDomains as $domain) {
                $domain = trim($domain);
                if ($domain && (str_ends_with($refererDomain, $domain) || $refererDomain === $domain)) {
                    $isAllowed = true;
                    break;
                }
            }

            if (!$isAllowed) {
                Log::warning("Domain lock violation for key: {$refererDomain}, video {$uuid}");
                abort(403, 'Domain not allowed');
            }
        }

        $key = $this->encryptionService->getKeyForStreaming($video, $keyId);
        if (!$key) {
            abort(404, 'Key not found');
        }

        return response($key, 200, [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * Record analytics event.
     */
    public function recordAnalytics(Request $request, string $uuid)
    {
        $video = Video::where('uuid', $uuid)->firstOrFail();

        $request->validate([
            'session_id' => 'required|string',
            'event' => 'required|string|in:play,pause,seek,quality_change,progress,complete',
            'position' => 'nullable|integer',
            'quality' => 'nullable|string',
        ]);

        $sessionId = $request->input('session_id');
        $event = $request->input('event');

        $analytics = $this->streamingService->recordAnalytics($video, $sessionId, [
            'device_type' => $this->detectDeviceType($request->userAgent()),
            'browser' => $this->detectBrowser($request->userAgent()),
            'os' => $this->detectOS($request->userAgent()),
        ]);

        // Handle specific events
        switch ($event) {
            case 'pause':
                $analytics->recordPause();
                break;
            case 'seek':
                $analytics->recordSeek();
                break;
            case 'quality_change':
                $analytics->recordQualityChange();
                break;
            case 'progress':
                $position = $request->input('position', 0);
                $analytics->updateProgress($position, $video->duration ?? 0);
                break;
            case 'play':
                // Update video view counts
                $video->increment('views_count');
                break;
        }

        return response()->json(['success' => true]);
    }

    /**
     * Detect device type from user agent.
     */
    protected function detectDeviceType(?string $userAgent): string
    {
        if (!$userAgent) return 'unknown';

        if (preg_match('/mobile/i', $userAgent)) return 'mobile';
        if (preg_match('/tablet|ipad/i', $userAgent)) return 'tablet';
        if (preg_match('/smart-tv|smarttv|googletv|appletv/i', $userAgent)) return 'tv';

        return 'desktop';
    }

    /**
     * Detect browser from user agent.
     */
    protected function detectBrowser(?string $userAgent): string
    {
        if (!$userAgent) return 'unknown';

        if (preg_match('/chrome/i', $userAgent) && !preg_match('/edge/i', $userAgent)) return 'Chrome';
        if (preg_match('/firefox/i', $userAgent)) return 'Firefox';
        if (preg_match('/safari/i', $userAgent) && !preg_match('/chrome/i', $userAgent)) return 'Safari';
        if (preg_match('/edge/i', $userAgent)) return 'Edge';
        if (preg_match('/opera|opr/i', $userAgent)) return 'Opera';

        return 'Other';
    }

    /**
     * Detect OS from user agent.
     */
    protected function detectOS(?string $userAgent): string
    {
        if (!$userAgent) return 'unknown';

        if (preg_match('/windows/i', $userAgent)) return 'Windows';
        if (preg_match('/macintosh|mac os/i', $userAgent)) return 'macOS';
        if (preg_match('/linux/i', $userAgent) && !preg_match('/android/i', $userAgent)) return 'Linux';
        if (preg_match('/android/i', $userAgent)) return 'Android';
        if (preg_match('/iphone|ipad|ipod/i', $userAgent)) return 'iOS';

        return 'Other';
    }
}
