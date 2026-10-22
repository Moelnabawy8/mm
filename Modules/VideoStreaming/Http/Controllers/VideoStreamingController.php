<?php

namespace Modules\VideoStreaming\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\VideoStreaming\Entities\Video;
use Modules\VideoStreaming\Services\VideoStreamingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VideoStreamingController extends Controller
{
    protected VideoStreamingService $streamingService;

    public function __construct(VideoStreamingService $streamingService)
    {
        $this->streamingService = $streamingService;
    }

    /**
     * Display video management dashboard.
     */
    public function index()
    {
        $videos = Video::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate(12);

        return view('videostreaming::videos.index', compact('videos'));
    }

    /**
     * Show upload form.
     */
    public function create()
    {
        return view('videostreaming::videos.create');
    }

    /**
     * Store uploaded video.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'video' => 'required|file|mimes:mp4,mov,avi,mkv,webm|max:5242880', // 5GB max
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'watermark_enabled' => 'nullable|boolean',
            'watermark_text' => 'nullable|string|max:100',
            'domain_lock_enabled' => 'nullable|boolean',
            'allowed_domains' => 'nullable|array',
        ]);

        try {
            $video = $this->streamingService->uploadVideo(
                $request->file('video'),
                auth()->id(),
                $request->only([
                    'title',
                    'description',
                    'watermark_enabled',
                    'watermark_text',
                    'domain_lock_enabled',
                    'allowed_domains',
                ])
            );

            // Dispatch processing job
            dispatch(new \Modules\VideoStreaming\Jobs\ProcessVideoJob($video));

            return response()->json([
                'success' => true,
                'message' => __('Video uploaded successfully. Processing will start shortly.'),
                'video' => [
                    'id' => $video->id,
                    'uuid' => $video->uuid,
                    'title' => $video->title,
                    'status' => $video->status,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('Upload failed: ') . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show video details.
     */
    public function show(string $uuid)
    {
        $video = Video::where('uuid', $uuid)
            ->where('user_id', auth()->id())
            ->with(['qualities', 'embedSettings', 'analytics'])
            ->firstOrFail();

        return view('videostreaming::videos.show', compact('video'));
    }

    /**
     * Update video settings.
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $video = Video::where('uuid', $uuid)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'watermark_enabled' => 'nullable|boolean',
            'watermark_text' => 'nullable|string|max:100',
            'domain_lock_enabled' => 'nullable|boolean',
            'allowed_domains' => 'nullable|array',
            'is_public' => 'nullable|boolean',
        ]);

        $video->update($request->only([
            'title',
            'description',
            'watermark_enabled',
            'watermark_text',
            'domain_lock_enabled',
            'allowed_domains',
            'is_public',
        ]));

        return response()->json([
            'success' => true,
            'message' => __('Video updated successfully.'),
        ]);
    }

    /**
     * Delete video.
     */
    public function destroy(string $uuid): JsonResponse
    {
        $video = Video::where('uuid', $uuid)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        if ($this->streamingService->deleteVideo($video)) {
            return response()->json([
                'success' => true,
                'message' => __('Video deleted successfully.'),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => __('Failed to delete video.'),
        ], 500);
    }

    /**
     * Get embed code.
     */
    public function embedCode(string $uuid): JsonResponse
    {
        $video = Video::where('uuid', $uuid)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'embed_url' => $video->embed_url,
            'iframe_code' => $video->iframe_code,
        ]);
    }

    /**
     * Get video status (for polling during processing).
     */
    public function status(string $uuid): JsonResponse
    {
        $video = Video::where('uuid', $uuid)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        return response()->json([
            'status' => $video->status,
            'processing_error' => $video->processing_error,
            'processed_at' => $video->processed_at?->toISOString(),
            'qualities' => $video->qualities->map(fn($q) => [
                'quality' => $q->quality,
                'status' => $q->status,
            ]),
        ]);
    }

    /**
     * Get video analytics.
     */
    public function analytics(string $uuid): JsonResponse
    {
        $video = Video::where('uuid', $uuid)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $analytics = $video->analytics()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as views, SUM(watch_duration) as total_watch_time')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get();

        return response()->json([
            'total_views' => $video->views_count,
            'unique_views' => $video->unique_views_count,
            'daily' => $analytics,
        ]);
    }
}
