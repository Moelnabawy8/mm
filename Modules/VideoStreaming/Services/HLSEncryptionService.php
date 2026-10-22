<?php

namespace Modules\VideoStreaming\Services;

use Modules\VideoStreaming\Entities\Video;
use Modules\VideoStreaming\Entities\VideoEncryptionKey;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class HLSEncryptionService
{
    protected int $keyRotationInterval;

    public function __construct()
    {
        $this->keyRotationInterval = config('videostreaming.hls.key_rotation_interval', 5);
    }

    /**
     * Generate encryption key and IV.
     */
    public function generateEncryptionKey(): array
    {
        return [
            'key' => random_bytes(16), // AES-128
            'iv' => random_bytes(16),
            'key_id' => Str::random(32),
        ];
    }

    /**
     * Create encryption key for video.
     */
    public function createKeyForVideo(Video $video, int $segmentStart = 0): VideoEncryptionKey
    {
        $keyData = $this->generateEncryptionKey();

        return VideoEncryptionKey::create([
            'video_id' => $video->id,
            'key_id' => $keyData['key_id'],
            'encryption_key' => $keyData['key'],
            'iv' => $keyData['iv'],
            'segment_start' => $segmentStart,
            'is_active' => true,
        ]);
    }

    /**
     * Create key info file for FFmpeg.
     */
    public function createKeyInfoFile(
        string $keyUrl,
        string $keyPath,
        string $ivHex,
        string $outputPath
    ): bool {
        try {
            // Key info file format:
            // Line 1: Key URL (for playlist)
            // Line 2: Key file path (for FFmpeg)
            // Line 3: IV (optional)
            
            $content = "{$keyUrl}\n{$keyPath}\n{$ivHex}";
            
            return file_put_contents($outputPath, $content) !== false;
        } catch (Exception $e) {
            Log::error('Failed to create key info file: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create key file (binary key).
     */
    public function createKeyFile(string $key, string $outputPath): bool
    {
        try {
            return file_put_contents($outputPath, $key) !== false;
        } catch (Exception $e) {
            Log::error('Failed to create key file: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Prepare encryption files for video processing.
     */
    public function prepareEncryptionFiles(Video $video, string $tempDir): array
    {
        // Create or get active encryption key
        $encryptionKey = $video->activeEncryptionKey ?? $this->createKeyForVideo($video);

        $keyPath = $tempDir . '/encryption.key';
        $keyInfoPath = $tempDir . '/key_info.txt';
        
        // Create key file
        $this->createKeyFile($encryptionKey->encryption_key, $keyPath);
        
        // Create key info file
        // The URL will be our API endpoint that validates and serves the key
        $keyUrl = route('video.stream.key', [
            'uuid' => $video->uuid,
            'keyId' => $encryptionKey->key_id,
        ]);
        
        $ivHex = bin2hex($encryptionKey->iv);
        
        $this->createKeyInfoFile($keyUrl, $keyPath, $ivHex, $keyInfoPath);

        return [
            'key' => $encryptionKey,
            'key_path' => $keyPath,
            'key_info_path' => $keyInfoPath,
            'iv_hex' => $ivHex,
        ];
    }

    /**
     * Validate and get encryption key for streaming.
     */
    public function getKeyForStreaming(Video $video, string $keyId): ?string
    {
        $key = VideoEncryptionKey::where('video_id', $video->id)
            ->where('key_id', $keyId)
            ->where('is_active', true)
            ->first();

        if (!$key) {
            return null;
        }

        return $key->encryption_key;
    }

    /**
     * Update HLS playlist with secure key URL.
     */
    public function updatePlaylistKeyUrl(
        string $playlistPath,
        string $videoUuid,
        string $keyId,
        string $token
    ): bool {
        try {
            if (!file_exists($playlistPath)) {
                return false;
            }

            $content = file_get_contents($playlistPath);
            
            // Generate secure key URL with token
            $secureKeyUrl = route('video.stream.key', [
                'uuid' => $videoUuid,
                'keyId' => $keyId,
            ]) . '?token=' . $token;

            // Replace existing EXT-X-KEY lines with secure URL
            $pattern = '/#EXT-X-KEY:METHOD=AES-128,URI="[^"]+"/';
            $replacement = '#EXT-X-KEY:METHOD=AES-128,URI="' . $secureKeyUrl . '"';
            
            $content = preg_replace($pattern, $replacement, $content);
            
            return file_put_contents($playlistPath, $content) !== false;
        } catch (Exception $e) {
            Log::error('Failed to update playlist key URL: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate signed key URL.
     */
    public function generateSignedKeyUrl(Video $video, string $keyId, string $token, int $expiry = 3600): string
    {
        $params = [
            'uuid' => $video->uuid,
            'keyId' => $keyId,
        ];
        
        $baseUrl = route('video.stream.key', $params);
        
        // Add token and expiry
        $signature = $this->generateSignature($video->uuid, $keyId, $token, $expiry);
        
        return $baseUrl . '?' . http_build_query([
            'token' => $token,
            'expires' => time() + $expiry,
            'signature' => $signature,
        ]);
    }

    /**
     * Generate signature for URL verification.
     */
    protected function generateSignature(string $uuid, string $keyId, string $token, int $expiry): string
    {
        $data = $uuid . $keyId . $token . $expiry;
        return hash_hmac('sha256', $data, config('app.key'));
    }

    /**
     * Verify URL signature.
     */
    public function verifySignature(string $uuid, string $keyId, string $token, int $expires, string $signature): bool
    {
        if ($expires < time()) {
            return false;
        }

        $expectedSignature = $this->generateSignature($uuid, $keyId, $token, $expires - time());
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Rotate encryption key for video.
     */
    public function rotateKey(Video $video, int $newSegmentStart): VideoEncryptionKey
    {
        // Deactivate old keys
        VideoEncryptionKey::where('video_id', $video->id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'segment_end' => $newSegmentStart - 1,
            ]);

        // Create new key
        return $this->createKeyForVideo($video, $newSegmentStart);
    }
}
