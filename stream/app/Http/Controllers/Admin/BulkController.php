<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityPreset;
use App\Models\Video;
use Illuminate\Http\Request;

class BulkController extends Controller
{
    public function applyPreset(Request $request)
    {
        $request->validate([
            'video_ids' => 'required|array',
            'video_ids.*' => 'uuid',
            'preset_id' => 'required|exists:security_presets,id',
        ]);

        $preset = SecurityPreset::findOrFail($request->preset_id);
        $videos = Video::whereIn('id', $request->video_ids)->get();

        foreach ($videos as $video) {
            $preset->applyToVideo($video);
        }

        return back()->with('success', "Preset applied to {$videos->count()} videos.");
    }

    public function disable(Request $request)
    {
        $request->validate(['video_ids' => 'required|array', 'video_ids.*' => 'uuid']);
        Video::whereIn('id', $request->video_ids)->update(['frozen' => true]);
        return back()->with('success', 'Selected videos have been frozen.');
    }

    public function enable(Request $request)
    {
        $request->validate(['video_ids' => 'required|array', 'video_ids.*' => 'uuid']);
        Video::whereIn('id', $request->video_ids)->update(['frozen' => false]);
        return back()->with('success', 'Selected videos have been unfrozen.');
    }

    public function delete(Request $request)
    {
        $request->validate(['video_ids' => 'required|array', 'video_ids.*' => 'uuid']);
        $count = Video::whereIn('id', $request->video_ids)->count();
        Video::whereIn('id', $request->video_ids)->delete();
        return back()->with('success', "{$count} videos deleted.");
    }

    public function updateWatermark(Request $request)
    {
        $request->validate([
            'video_ids' => 'required|array',
            'video_ids.*' => 'uuid',
            'watermark_enabled' => 'required|boolean',
            'watermark_type' => 'nullable|string',
            'watermark_opacity' => 'nullable|numeric|min:0|max:1',
        ]);

        $data = ['watermark_enabled' => $request->watermark_enabled];
        if ($request->watermark_type) $data['watermark_type'] = $request->watermark_type;
        if ($request->watermark_opacity) $data['watermark_opacity'] = $request->watermark_opacity;

        Video::whereIn('id', $request->video_ids)->update($data);
        return back()->with('success', 'Watermark settings updated for selected videos.');
    }
}
