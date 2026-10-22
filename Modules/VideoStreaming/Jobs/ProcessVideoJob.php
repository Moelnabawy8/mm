<?php

namespace Modules\VideoStreaming\Jobs;

use Modules\VideoStreaming\Entities\Video;
use Modules\VideoStreaming\Services\VideoStreamingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 hour timeout
    public int $tries = 3;
    public int $maxExceptions = 2;

    protected Video $video;

    /**
     * Create a new job instance.
     */
    public function __construct(Video $video)
    {
        $this->video = $video;
        $this->onQueue('video-processing');
    }

    /**
     * Execute the job.
     */
    public function handle(VideoStreamingService $streamingService): void
    {
        Log::info("Starting video processing for: {$this->video->uuid}");

        try {
            $result = $streamingService->processVideo($this->video);

            if ($result) {
                Log::info("Video processing completed: {$this->video->uuid}");
            } else {
                Log::error("Video processing failed: {$this->video->uuid}");
            }
        } catch (\Exception $e) {
            Log::error("Video processing exception: {$e->getMessage()}");
            
            $this->video->update([
                'status' => 'failed',
                'processing_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Video processing job failed permanently: {$this->video->uuid} - {$exception->getMessage()}");

        $this->video->update([
            'status' => 'failed',
            'processing_error' => $exception->getMessage(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['video-processing', 'video:' . $this->video->uuid];
    }
}
