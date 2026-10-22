<?php

namespace App\Services\Storage;

use App\Exceptions\StorageQuotaExceededException;
use Illuminate\Support\Facades\Log;

class StorageManager
{
    protected StorageInterface $primary;
    protected LocalStorageService $fallback;

    public function __construct()
    {
        $driver = config('securestream.storage_driver', 'local');

        $this->fallback = new LocalStorageService();

        $this->primary = match ($driver) {
            'google' => new GoogleDriveStorageService(),
            default => $this->fallback,
        };
    }

    public function driver(): StorageInterface
    {
        return $this->primary;
    }

    public function upload(string $localPath, string $remotePath): array
    {
        try {
            $result = $this->primary->upload($localPath, $remotePath);
            if ($result) {
                return [
                    'success' => true,
                    'driver' => $this->primary->getDriver(),
                    'fallback' => false,
                ];
            }
        } catch (StorageQuotaExceededException $e) {
            Log::warning('Primary storage quota exceeded, falling back to local', [
                'primary_driver' => $this->primary->getDriver(),
                'path' => $remotePath,
            ]);

            if ($this->primary->getDriver() !== 'local') {
                $result = $this->fallback->upload($localPath, $remotePath);
                if ($result) {
                    return [
                        'success' => true,
                        'driver' => 'local',
                        'fallback' => true,
                    ];
                }
            }
        }

        return [
            'success' => false,
            'driver' => $this->primary->getDriver(),
            'fallback' => false,
        ];
    }

    public function download(string $remotePath, string $driver = null): ?string
    {
        $service = $this->resolveDriver($driver);
        return $service->download($remotePath);
    }

    public function stream(string $remotePath, string $driver = null): mixed
    {
        $service = $this->resolveDriver($driver);
        return $service->stream($remotePath);
    }

    public function delete(string $remotePath, string $driver = null): bool
    {
        $service = $this->resolveDriver($driver);
        return $service->delete($remotePath);
    }

    public function exists(string $remotePath, string $driver = null): bool
    {
        $service = $this->resolveDriver($driver);
        return $service->exists($remotePath);
    }

    protected function resolveDriver(?string $driver): StorageInterface
    {
        if ($driver === null) {
            return $this->primary;
        }

        return match ($driver) {
            'google' => new GoogleDriveStorageService(),
            'local' => $this->fallback,
            default => $this->primary,
        };
    }
}
