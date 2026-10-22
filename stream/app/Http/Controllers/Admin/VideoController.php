<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessVideoJob;
use App\Models\SecurityPreset;
use App\Models\Video;
use App\Services\StreamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class VideoController extends Controller
{
    public function __construct(protected StreamService $streamService) {}

    public function index(Request $request)
    {
        $query = Video::with('creator')->withCount('activeSessions');

        if ($search = $request->input('search')) {
            $query->where('title', 'like', "%{$search}%");
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($request->input('frozen') === '1') {
            $query->where('frozen', true);
        }

        $videos = $query->latest()->paginate(15)->withQueryString();
        $presets = SecurityPreset::all();

        return view('admin.videos.index', compact('videos', 'presets'));
    }

    public function create()
    {
        return view('admin.videos.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'visibility' => 'required|in:public,private',
        ]);

        $validated['created_by'] = Auth::id();
        $validated['status'] = 'pending';
        $validated['storage_driver'] = config('securestream.storage_driver', 'local');

        $video = Video::create($validated);

        return response()->json([
            'success' => true,
            'video_id' => $video->id,
            'upload_url' => route('admin.videos.upload-chunk', $video),
        ]);
    }

    public function uploadChunk(Request $request, Video $video)
    {
        $request->validate([
            'chunk' => 'required|file',
            'chunk_index' => 'required|integer|min:0',
            'total_chunks' => 'required|integer|min:1',
        ]);

        $chunkDir = storage_path('app/chunks/' . $video->id);
        if (!is_dir($chunkDir)) mkdir($chunkDir, 0755, true);

        $chunkIndex = $request->input('chunk_index');
        $totalChunks = $request->input('total_chunks');

        $request->file('chunk')->move($chunkDir, "chunk_{$chunkIndex}");

        $uploadedChunks = count(glob($chunkDir . '/chunk_*'));

        if ($uploadedChunks >= $totalChunks) {
            $finalPath = storage_path('app/uploads/' . $video->id . '/' . Str::uuid() . '.mp4');
            $finalDir = dirname($finalPath);
            if (!is_dir($finalDir)) mkdir($finalDir, 0755, true);

            $outputFile = fopen($finalPath, 'wb');
            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkPath = $chunkDir . "/chunk_{$i}";
                if (file_exists($chunkPath)) {
                    $chunkStream = fopen($chunkPath, 'rb');
                    stream_copy_to_stream($chunkStream, $outputFile);
                    fclose($chunkStream);
                    unlink($chunkPath);
                }
            }
            fclose($outputFile);
            @rmdir($chunkDir);

            $video->update(['file_path' => $finalPath]);
            ProcessVideoJob::dispatch($video->id, $finalPath);

            return response()->json([
                'success' => true,
                'message' => 'Upload complete. Processing started.',
                'status' => 'processing',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "Chunk {$chunkIndex} uploaded.",
            'uploaded' => $uploadedChunks,
            'total' => $totalChunks,
        ]);
    }

    public function edit(Video $video)
    {
        $presets = SecurityPreset::all();
        return view('admin.videos.edit', compact('video', 'presets'));
    }

    public function update(Request $request, Video $video)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'visibility' => 'required|in:public,private',
        ]);

        $video->update($validated);

        return redirect()->route('admin.videos.show', $video)
            ->with('success', 'Video updated successfully.');
    }

    public function destroy(Video $video)
    {
        $video->delete();
        return redirect()->route('admin.videos.index')
            ->with('success', 'Video deleted successfully.');
    }

    public function show(Video $video)
    {
        $video->load(['accessLogs' => fn($q) => $q->latest()->limit(50), 'allowedDomains', 'activeSessions', 'blockedIps', 'blockedFingerprints']);
        $videoHash = $this->streamService->generateVideoHash($video->id);
        $embedCode = $this->generateEmbedCode($video);
        $shareLink = route('watch.show', ['hash' => $videoHash]);
        $presets = SecurityPreset::all();

        // Analytics data
        $viewsToday = $video->accessLogs()->whereDate('created_at', today())->where('blocked', false)->count();
        $viewsWeek = $video->accessLogs()->where('created_at', '>=', now()->subWeek())->where('blocked', false)->count();
        $blockedAttempts = $video->accessLogs()->where('blocked', true)->count();
        $countries = $video->accessLogs()->whereNotNull('country')->where('blocked', false)
            ->selectRaw('country, count(*) as total')->groupBy('country')->orderByDesc('total')->limit(10)->get();

        return view('admin.videos.show', compact(
            'video', 'embedCode', 'videoHash', 'shareLink', 'presets',
            'viewsToday', 'viewsWeek', 'blockedAttempts', 'countries'
        ));
    }

    public function status(Video $video)
    {
        return response()->json([
            'status' => $video->status,
            'duration' => $video->duration,
            'resolution' => $video->resolution,
        ]);
    }

    public function addDomain(Request $request, Video $video)
    {
        $request->validate(['domain' => 'required|string|max:255']);
        $video->allowedDomains()->firstOrCreate(['domain' => $request->input('domain')]);
        return redirect()->route('admin.videos.show', $video)->with('success', 'Domain added.');
    }

    public function removeDomain(Video $video, $domainId)
    {
        $video->allowedDomains()->where('id', $domainId)->delete();
        return redirect()->route('admin.videos.show', $video)->with('success', 'Domain removed.');
    }

    public function freeze(Video $video)
    {
        $video->update(['frozen' => true]);
        $video->activeSessions()->update(['status' => 'terminated']);
        return back()->with('success', '🚨 Video FROZEN. All sessions terminated.');
    }

    public function unfreeze(Video $video)
    {
        $video->update(['frozen' => false]);
        return back()->with('success', 'Video unfrozen and accessible again.');
    }

    public function updateSecurity(Request $request, Video $video)
    {
        $validated = $request->validate([
            'max_concurrent_streams' => 'nullable|integer|min:0',
            'max_total_views' => 'nullable|integer|min:0',
            'expires_at' => 'nullable|date',
            'token_duration' => 'nullable|integer|min:1|max:1440',
            'ip_binding_enabled' => 'nullable|boolean',
            'fingerprint_lock' => 'nullable|boolean',
            'geo_restriction_enabled' => 'nullable|boolean',
            'allowed_countries' => 'nullable|array',
            'key_rotation_enabled' => 'nullable|boolean',
        ]);

        // Convert checkbox-style inputs
        foreach (['ip_binding_enabled', 'fingerprint_lock', 'geo_restriction_enabled', 'key_rotation_enabled'] as $field) {
            $validated[$field] = $request->has($field);
        }

        $video->update($validated);
        return back()->with('success', 'Security settings updated.');
    }

    public function updateWatermark(Request $request, Video $video)
    {
        $validated = $request->validate([
            'watermark_enabled' => 'nullable|boolean',
            'watermark_text' => 'nullable|string|max:255',
            'watermark_type' => 'nullable|in:ip,email,text,session,timestamp',
            'watermark_opacity' => 'nullable|numeric|min:0.05|max:1',
            'watermark_position' => 'nullable|in:random,top-left,top-right,bottom-left,bottom-right,center',
            'watermark_animation' => 'nullable|in:move,static,fade',
        ]);

        $validated['watermark_enabled'] = $request->has('watermark_enabled');
        $video->update($validated);
        return back()->with('success', 'Watermark settings updated.');
    }

    public function applyPreset(Request $request, Video $video)
    {
        $request->validate(['preset_id' => 'required|exists:security_presets,id']);
        $preset = SecurityPreset::findOrFail($request->preset_id);
        $preset->applyToVideo($video);
        return back()->with('success', "Preset \"{$preset->name}\" applied.");
    }

    protected function generateEmbedCode(Video $video): string
    {
        $hash = $this->streamService->generateVideoHash($video->id);
        $url = route('embed.show', ['hash' => $hash]);
        return '<iframe src="' . $url . '" width="800" height="450" frameborder="0" allowfullscreen></iframe>';
    }
}
