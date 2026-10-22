<?php

namespace App\Jobs;

use App\Models\Video;
use App\Services\HlsEncryptionService;
use App\Services\Storage\StorageManager;
use App\Services\VideoProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 3600;

    public function __construct(
        protected string $videoId,
        protected string $inputPath
    ) {}

    public function handle(
        VideoProcessingService $videoService,
        HlsEncryptionService $hlsService,
        StorageManager $storageManager
    ): void {
        $video = Video::find($this->videoId);
        if (!$video) {
            Log::error('ProcessVideoJob: Video not found', ['video_id' => $this->videoId]);
            return;
        }

        $video->update(['status' => 'processing']);

        try {
            $duration = $videoService->getVideoDuration($this->inputPath);
            $resolution = $videoService->getVideoResolution($this->inputPath);

            $encryption = $hlsService->generateEncryptionKey($this->videoId);

            $keyUrl = url("/api/hls-key/{$this->videoId}/TOKEN_PLACEHOLDER");
            $keyInfoPath = $hlsService->createKeyInfoFile(
                $this->videoId,
                $encryption['key_path'],
                $encryption['iv_hex'],
                $keyUrl
            );

            $hlsOutputDir = storage_path('app/hls/' . $this->videoId);

            $success = $videoService->convertToHls(
                $this->inputPath,
                $hlsOutputDir,
                $encryption['key_path'],
                $keyInfoPath
            );

            if (!$success) {
                $video->update(['status' => 'failed']);
                Log::error('ProcessVideoJob: HLS conversion failed', ['video_id' => $this->videoId]);
                return;
            }

            $hlsFiles = glob($hlsOutputDir . '/*');
            $storageDriver = config('securestream.storage_driver', 'local');
            $actualDriver = $storageDriver;

            foreach ($hlsFiles as $file) {
                if (is_file($file)) {
                    $remotePath = 'videos/' . $this->videoId . '/hls/' . basename($file);
                    $result = $storageManager->upload($file, $remotePath);

                    if ($result['fallback']) {
                        $actualDriver = 'local';
                    }

                    if (!$result['success']) {
                        $video->update(['status' => 'failed']);
                        Log::error('ProcessVideoJob: Upload failed', [
                            'video_id' => $this->videoId,
                            'file' => basename($file),
                        ]);
                        return;
                    }
                }
            }

            $video->update([
                'status' => 'ready',
                'duration' => $duration,
                'resolution' => $resolution,
                'hls_path' => 'videos/' . $this->videoId . '/hls/playlist.m3u8',
                'encryption_key_path' => 'encryption_keys/' . $this->videoId . '/encryption.key',
                'storage_driver' => $actualDriver,
            ]);

            if (file_exists($this->inputPath)) {
                unlink($this->inputPath);
            }

            $this->cleanupTempHls($hlsOutputDir);

            Log::info('ProcessVideoJob: Video processed successfully', [
                'video_id' => $this->videoId,
                'driver' => $actualDriver,
            ]);

        } catch (\Exception $e) {
            $video->update(['status' => 'failed']);
            Log::error('ProcessVideoJob: Exception', [
                'video_id' => $this->videoId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function cleanupTempHls(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($dir);
    }

    public function failed(\Throwable $exception): void
    {
        $video = Video::find($this->videoId);
        if ($video) {
            $video->update(['status' => 'failed']);
        }

        Log::error('ProcessVideoJob: Job failed permanently', [
            'video_id' => $this->videoId,
            'error' => $exception->getMessage(),
        ]);
    }
}
