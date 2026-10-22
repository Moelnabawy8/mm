<?php

namespace Modules\VideoStreaming\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class SegmentCacheService
{
    protected string $cacheDriver;
    protected string $diskCachePath;
    protected int $cacheTtl;
    protected int $maxDiskCacheSizeMb;

    public function __construct()
    {
        $this->cacheDriver = config('videostreaming.cache.driver', 'file');
        $this->diskCachePath = storage_path('app/cache/segments');
        $this->cacheTtl = config('videostreaming.cache.segment_ttl', 86400);
        $this->maxDiskCacheSizeMb = config('videostreaming.cache.max_disk_size_mb', 5000);

        if (!is_dir($this->diskCachePath)) {
            mkdir($this->diskCachePath, 0755, true);
        }
    }

    public function getCacheKey(string $videoUuid, string $quality, int $segmentIndex): string
    {
        return "segment:{$videoUuid}:{$quality}:{$segmentIndex}";
    }

    public function getFileIdCacheKey(string $videoUuid, string $quality, int $segmentIndex): string
    {
        return "segment_file_id:{$videoUuid}:{$quality}:{$segmentIndex}";
    }

    public function getDiskCachePath(string $videoUuid, string $quality, int $segmentIndex): string
    {
        $subDir = $this->diskCachePath . '/' . $videoUuid . '/' . $quality;
        if (!is_dir($subDir)) {
            mkdir($subDir, 0755, true);
        }
        return $subDir . '/segment_' . str_pad($segmentIndex, 5, '0', STR_PAD_LEFT) . '.ts';
    }

    public function get(string $videoUuid, string $quality, int $segmentIndex): ?string
    {
        $cacheKey = $this->getCacheKey($videoUuid, $quality, $segmentIndex);

        if ($this->cacheDriver === 'redis') {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                Log::debug("Segment cache HIT (Redis): {$cacheKey}");
                return $cached;
            }
        }

        $diskPath = $this->getDiskCachePath($videoUuid, $quality, $segmentIndex);
        if (file_exists($diskPath)) {
            $content = file_get_contents($diskPath);
            Log::debug("Segment cache HIT (Disk): {$diskPath}");

            if ($this->cacheDriver === 'redis' && strlen($content) < 1048576) {
                Cache::put($cacheKey, $content, $this->cacheTtl);
            }

            return $content;
        }

        Log::debug("Segment cache MISS: {$cacheKey}");
        return null;
    }

    public function put(string $videoUuid, string $quality, int $segmentIndex, string $content): void
    {
        $cacheKey = $this->getCacheKey($videoUuid, $quality, $segmentIndex);

        $diskPath = $this->getDiskCachePath($videoUuid, $quality, $segmentIndex);
        file_put_contents($diskPath, $content);

        if ($this->cacheDriver === 'redis' && strlen($content) < 1048576) {
            Cache::put($cacheKey, $content, $this->cacheTtl);
        }

        $this->enforceMaxDiskSize();
    }

    public function putFileId(string $videoUuid, string $quality, int $segmentIndex, string $fileId): void
    {
        $cacheKey = $this->getFileIdCacheKey($videoUuid, $quality, $segmentIndex);
        Cache::put($cacheKey, $fileId, $this->cacheTtl * 2);
    }

    public function getFileId(string $videoUuid, string $quality, int $segmentIndex): ?string
    {
        $cacheKey = $this->getFileIdCacheKey($videoUuid, $quality, $segmentIndex);
        return Cache::get($cacheKey);
    }

    public function warmupSegments(string $videoUuid, string $quality, int $startSegment, int $count): void
    {
        Log::info("Warming up segments for {$videoUuid} {$quality}: {$startSegment} to " . ($startSegment + $count - 1));
    }

    public function clearVideo(string $videoUuid): void
    {
        $videoDir = $this->diskCachePath . '/' . $videoUuid;
        if (is_dir($videoDir)) {
            $this->deleteDirectory($videoDir);
        }

        $pattern = "segment:{$videoUuid}:*";
        if ($this->cacheDriver === 'redis') {
            $keys = Cache::getRedis()->keys($pattern);
            if (!empty($keys)) {
                Cache::getRedis()->del($keys);
            }
        }
    }

    protected function enforceMaxDiskSize(): void
    {
        $totalSize = $this->getDirectorySize($this->diskCachePath);
        $maxSize = $this->maxDiskCacheSizeMb * 1024 * 1024;

        if ($totalSize > $maxSize) {
            Log::info("Cache size exceeded ({$totalSize} bytes), cleaning old segments");
            $this->cleanOldestSegments($totalSize - $maxSize);
        }
    }

    protected function getDirectorySize(string $path): int
    {
        $size = 0;
        if (is_dir($path)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($files as $file) {
                $size += $file->getSize();
            }
        }
        return $size;
    }

    protected function cleanOldestSegments(int $bytesToFree): void
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->diskCachePath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = [
                    'path' => $file->getPathname(),
                    'atime' => $file->getATime(),
                    'size' => $file->getSize(),
                ];
            }
        }

        usort($files, fn($a, $b) => $a['atime'] <=> $b['atime']);

        $freed = 0;
        foreach ($files as $file) {
            if ($freed >= $bytesToFree) {
                break;
            }
            unlink($file['path']);
            $freed += $file['size'];
        }

        Log::info("Freed {$freed} bytes from segment cache");
    }

    protected function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
