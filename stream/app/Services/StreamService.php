<?php

namespace App\Services;

use App\Models\AllowedDomain;
use App\Models\Video;
use App\Models\VideoAccessLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class StreamService
{
    public function generateStreamToken(string $videoId): string
    {
        $payload = [
            'video_id' => $videoId,
            'expires_at' => now()->addMinutes((int) config('securestream.stream_token_expiry', 10))->timestamp,
        ];

        return base64_encode(Crypt::encryptString(json_encode($payload)));
    }

    public function validateStreamToken(string $token): ?array
    {
        try {
            $decrypted = Crypt::decryptString(base64_decode($token));
            $payload = json_decode($decrypted, true);

            if (!$payload || !isset($payload['video_id'], $payload['expires_at'])) {
                return null;
            }

            if ($payload['expires_at'] < now()->timestamp) {
                return null;
            }

            return $payload;
        } catch (\Exception $e) {
            Log::warning('Stream token validation failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function generateHlsKeyToken(string $videoId): string
    {
        $payload = [
            'video_id' => $videoId,
            'expires_at' => now()->addMinutes((int) config('securestream.hls_key_expiry', 5))->timestamp,
            'type' => 'hls_key',
        ];

        return base64_encode(Crypt::encryptString(json_encode($payload)));
    }

    public function validateHlsKeyToken(string $token): ?array
    {
        try {
            $decrypted = Crypt::decryptString(base64_decode($token));
            $payload = json_decode($decrypted, true);

            if (!$payload || $payload['type'] !== 'hls_key') {
                return null;
            }

            if ($payload['expires_at'] < now()->timestamp) {
                return null;
            }

            return $payload;
        } catch (\Exception $e) {
            Log::warning('HLS key token validation failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function verifyDomain(Request $request, ?string $videoId = null): bool
    {
        $referer = $request->header('Referer') ?? $request->header('Origin');

        // No referer = direct access (browser URL bar, API client, etc.)
        // Allow unless the video has specific domain restrictions
        if (empty($referer)) {
            if ($videoId) {
                $hasRestrictions = AllowedDomain::where('video_id', $videoId)->exists();
                // If video has per-domain restrictions, block direct access
                // (embeds must come from an allowed domain)
                return !$hasRestrictions;
            }
            return true;
        }

        $refererHost = parse_url($referer, PHP_URL_HOST);
        if (!$refererHost) {
            return true; // Malformed referer — allow through
        }

        // Always allow from localhost / 127.0.0.1 (local development)
        if (in_array($refererHost, ['localhost', '127.0.0.1'])) {
            return true;
        }

        // Always allow requests originating from the same server
        // (covers APP_URL mismatch between localhost and 127.0.0.1)
        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        if ($refererHost === $appHost) {
            return true;
        }

        // Also allow when referer matches the actual request host
        // (handles cases where APP_URL differs from actual hostname)
        if ($refererHost === $request->getHost()) {
            return true;
        }

        // Check per-video domains first
        if ($videoId) {
            $videoSpecificDomains = AllowedDomain::where('video_id', $videoId)->pluck('domain')->toArray();
            if (!empty($videoSpecificDomains)) {
                return in_array($refererHost, $videoSpecificDomains);
            }
        }

        // Fall back to global domains (where video_id is null)
        $globalDomains = AllowedDomain::whereNull('video_id')->pluck('domain')->toArray();

        // If no global domains are configured, allow all referers
        if (empty($globalDomains)) {
            return true;
        }

        return in_array($refererHost, $globalDomains);
    }

    public function logAccess(Request $request, string $videoId, bool $blocked = false): void
    {
        VideoAccessLog::create([
            'video_id' => $videoId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer' => $request->header('Referer'),
            'access_time' => now(),
            'blocked' => $blocked,
        ]);
    }

    public function generateSignedStreamUrl(string $videoId): string
    {
        $token = $this->generateStreamToken($videoId);

        return URL::signedRoute('stream.play', [
            'token' => $token,
        ], now()->addMinutes((int) config('securestream.stream_token_expiry', 10)));
    }

    public function generateSignedHlsKeyUrl(string $videoId): string
    {
        $token = $this->generateHlsKeyToken($videoId);

        return URL::signedRoute('stream.hls-key', [
            'videoId' => $videoId,
            'token' => $token,
        ], now()->addMinutes((int) config('securestream.hls_key_expiry', 5)));
    }

    public function getSecureStreamHeaders(): array
    {
        return [
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Robots-Tag' => 'noindex, nofollow',
        ];
    }

    public function generateVideoHash(string $videoId): string
    {
        return base64_encode(Crypt::encryptString($videoId));
    }

    public function decodeVideoHash(string $hash): ?string
    {
        try {
            return Crypt::decryptString(base64_decode($hash));
        } catch (\Exception $e) {
            return null;
        }
    }
}
