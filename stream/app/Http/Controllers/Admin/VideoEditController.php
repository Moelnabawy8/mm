<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessVideoJob;
use App\Models\Video;
use Illuminate\Http\Request;

class VideoEditController extends Controller
{
    public function regenerateHls(Video $video)
    {
        if (!$video->file_path || !file_exists($video->file_path)) {
            return back()->with('error', 'Original video file not found.');
        }

        $video->update(['status' => 'processing']);
        ProcessVideoJob::dispatch($video->id, $video->file_path);

        return back()->with('success', 'HLS regeneration started.');
    }

    public function regenerateThumbnail(Video $video)
    {
        if (!$video->file_path || !file_exists($video->file_path)) {
            return back()->with('error', 'Video file not found.');
        }

        $ffmpeg = config('securestream.ffmpeg_path', '/usr/local/bin/ffmpeg');
        $thumbPath = storage_path('app/thumbnails/' . $video->id . '.jpg');
        $thumbDir = dirname($thumbPath);
        if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);

        $time = max(1, intval(($video->duration ?? 10) / 4));
        $cmd = "{$ffmpeg} -y -i " . escapeshellarg($video->file_path)
             . " -ss {$time} -vframes 1 -q:v 2 " . escapeshellarg($thumbPath) . " 2>&1";
        exec($cmd, $output, $returnCode);

        if ($returnCode === 0 && file_exists($thumbPath)) {
            $video->update(['thumbnail_path' => $thumbPath]);
            return back()->with('success', 'Thumbnail regenerated.');
        }

        return back()->with('error', 'Thumbnail generation failed.');
    }

    public function extractFrame(Request $request, Video $video)
    {
        $request->validate(['time' => 'required|numeric|min:0']);

        if (!$video->file_path || !file_exists($video->file_path)) {
            return back()->with('error', 'Video file not found.');
        }

        $ffmpeg = config('securestream.ffmpeg_path', '/usr/local/bin/ffmpeg');
        $thumbPath = storage_path('app/thumbnails/' . $video->id . '.jpg');
        $thumbDir = dirname($thumbPath);
        if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);

        $time = intval($request->time);
        $cmd = "{$ffmpeg} -y -i " . escapeshellarg($video->file_path)
             . " -ss {$time} -vframes 1 -q:v 2 " . escapeshellarg($thumbPath) . " 2>&1";
        exec($cmd, $output, $returnCode);

        if ($returnCode === 0 && file_exists($thumbPath)) {
            $video->update(['thumbnail_path' => $thumbPath]);
            return back()->with('success', "Frame extracted at {$time}s and set as thumbnail.");
        }

        return back()->with('error', 'Frame extraction failed.');
    }

    public function trim(Request $request, Video $video)
    {
        $request->validate([
            'start_time' => 'required|numeric|min:0',
            'end_time' => 'required|numeric|gt:start_time',
        ]);

        if (!$video->file_path || !file_exists($video->file_path)) {
            return back()->with('error', 'Video file not found.');
        }

        $ffmpeg = config('securestream.ffmpeg_path', '/usr/local/bin/ffmpeg');
        $outputPath = storage_path('app/uploads/' . $video->id . '/trimmed_' . time() . '.mp4');

        $start = $request->start_time;
        $duration = $request->end_time - $request->start_time;

        $cmd = "{$ffmpeg} -y -i " . escapeshellarg($video->file_path)
             . " -ss {$start} -t {$duration} -c copy " . escapeshellarg($outputPath) . " 2>&1";
        exec($cmd, $output, $returnCode);

        if ($returnCode === 0 && file_exists($outputPath)) {
            $oldPath = $video->file_path;
            $video->update(['file_path' => $outputPath, 'status' => 'processing']);
            ProcessVideoJob::dispatch($video->id, $outputPath);
            @unlink($oldPath);
            return back()->with('success', 'Video trimmed. Re-processing HLS.');
        }

        return back()->with('error', 'Video trim failed.');
    }

    public function changeBitrate(Request $request, Video $video)
    {
        $request->validate(['bitrate' => 'required|string|in:500k,1000k,2000k,4000k,8000k']);

        if (!$video->file_path || !file_exists($video->file_path)) {
            return back()->with('error', 'Video file not found.');
        }

        $ffmpeg = config('securestream.ffmpeg_path', '/usr/local/bin/ffmpeg');
        $outputPath = storage_path('app/uploads/' . $video->id . '/reencoded_' . time() . '.mp4');

        $cmd = "{$ffmpeg} -y -i " . escapeshellarg($video->file_path)
             . " -b:v {$request->bitrate} -c:a copy " . escapeshellarg($outputPath) . " 2>&1";
        exec($cmd, $output, $returnCode);

        if ($returnCode === 0 && file_exists($outputPath)) {
            $video->update(['file_path' => $outputPath, 'status' => 'processing']);
            ProcessVideoJob::dispatch($video->id, $outputPath);
            return back()->with('success', "Re-encoding at {$request->bitrate}. Processing started.");
        }

        return back()->with('error', 'Bitrate change failed.');
    }

    public function replaceSource(Request $request, Video $video)
    {
        $request->validate(['video_file' => 'required|file|mimes:mp4,mkv,avi,mov|max:512000']);

        $file = $request->file('video_file');
        $filePath = storage_path('app/uploads/' . $video->id . '/replaced_' . time() . '.mp4');
        $fileDir = dirname($filePath);
        if (!is_dir($fileDir)) mkdir($fileDir, 0755, true);
        $file->move($fileDir, basename($filePath));

        $video->update(['file_path' => $filePath, 'status' => 'processing']);
        ProcessVideoJob::dispatch($video->id, $filePath);

        return back()->with('success', 'Source replaced. Re-processing started.');
    }
}
