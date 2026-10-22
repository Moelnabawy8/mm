<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HlsEncryptionService
{
    public function generateEncryptionKey(string $videoId): array
    {
        $key = openssl_random_pseudo_bytes(16);
        $iv = openssl_random_pseudo_bytes(16);

        $keyDir = storage_path('app/encryption_keys/' . $videoId);
        if (!is_dir($keyDir)) {
            mkdir($keyDir, 0755, true);
        }

        $keyPath = $keyDir . '/encryption.key';
        $ivHex = bin2hex($iv);

        file_put_contents($keyPath, $key);

        return [
            'key_path' => $keyPath,
            'iv_hex' => $ivHex,
            'key_dir' => $keyDir,
        ];
    }

    public function createKeyInfoFile(string $videoId, string $keyPath, string $ivHex, string $keyUrl = ''): string
    {
        if (empty($keyUrl)) {
            $keyUrl = url("/api/hls-key/{$videoId}/" . Str::random(32));
        }

        $keyInfoPath = dirname($keyPath) . '/key_info.txt';

        $content = implode("\n", [
            $keyUrl,
            $keyPath,
            $ivHex,
        ]);

        file_put_contents($keyInfoPath, $content);

        return $keyInfoPath;
    }

    public function getEncryptionKey(string $videoId): ?string
    {
        $keyPath = storage_path('app/encryption_keys/' . $videoId . '/encryption.key');

        if (!file_exists($keyPath)) {
            Log::warning('Encryption key not found', ['video_id' => $videoId]);
            return null;
        }

        return file_get_contents($keyPath);
    }

    public function deleteEncryptionKey(string $videoId): bool
    {
        $keyDir = storage_path('app/encryption_keys/' . $videoId);

        if (!is_dir($keyDir)) {
            return true;
        }

        $files = glob($keyDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return rmdir($keyDir);
    }
}
