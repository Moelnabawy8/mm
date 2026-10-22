<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TimeTracker;
use App\Models\TrackPhoto;
use App\Models\Utility;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TimeTrackerApiController extends Controller
{
    use ApiResponser;

    public function startTimeTracker(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'project_id' => 'nullable|exists:projects,id',
            'task_id' => 'nullable|exists:project_tasks,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Check if user already has an active tracker
        $activeTracker = TimeTracker::where('user_id', Auth::user()->id)
            ->where('created_by', Auth::user()->creatorId())
            ->whereNull('end_time')
            ->first();

        if ($activeTracker) {
            return $this->error('You already have an active time tracker. Please stop it first.', 400);
        }

        $tracker = new TimeTracker();
        $tracker->name = $request->name;
        $tracker->project_id = $request->project_id;
        $tracker->task_id = $request->task_id;
        $tracker->user_id = Auth::user()->id;
        $tracker->created_by = Auth::user()->creatorId();
        $tracker->start_time = date('Y-m-d H:i:s');
        $tracker->save();

        return $this->success(['tracker' => $tracker], 'Time tracking started.');
    }

    public function stopTimeTracker(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'tracker_id' => 'required|exists:time_trackers,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $tracker = TimeTracker::where('id', $request->tracker_id)
            ->where('user_id', Auth::user()->id)
            ->where('created_by', Auth::user()->creatorId())
            ->whereNull('end_time')
            ->first();

        if (!$tracker) {
            return $this->error('No active tracker found.', 404);
        }

        $tracker->end_time = date('Y-m-d H:i:s');
        $tracker->total_time = Utility::diffance_to_time($tracker->start_time, $tracker->end_time);
        $tracker->is_active = 0; // the web tracker reads is_active, not end_time
        $tracker->save();

        return $this->success(['tracker' => $tracker], 'Time tracking stopped.');
    }

    public function getCurrentTimeTracker()
    {
        $tracker = TimeTracker::where('user_id', Auth::user()->id)
            ->where('created_by', Auth::user()->creatorId())
            ->whereNull('end_time')
            ->with(['project', 'task'])
            ->first();

        if (!$tracker) {
            return $this->success(['tracker' => null], 'No active tracker found.');
        }

        return $this->success(['tracker' => $tracker], 'Current tracker retrieved.');
    }

    public function getTimeTrackerHistory()
    {
        if (Auth::user()->type == 'company') {
            $trackers = TimeTracker::where('created_by', Auth::user()->creatorId())
                ->with(['project', 'task', 'user'])
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            $trackers = TimeTracker::where('user_id', Auth::user()->id)
                ->where('created_by', Auth::user()->creatorId())
                ->with(['project', 'task'])
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return $this->success(['trackers' => $trackers], 'Tracker history retrieved.');
    }

    public function deleteTracker($id)
    {
        $tracker = TimeTracker::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$tracker) {
            return $this->error('Tracker not found.', 404);
        }

        // Check permissions for non-company users
        if (Auth::user()->type != 'company' && $tracker->user_id != Auth::user()->id) {
            return $this->error('Permission denied.', 403);
        }

        $tracker->delete();

        return $this->success([], 'Tracker deleted successfully.');
    }

    public function getTrackerImages($trackerId)
    {
        $tracker = TimeTracker::where('id', $trackerId)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$tracker) {
            return $this->error('Tracker not found.', 404);
        }

        // Check permissions for non-company users
        if (Auth::user()->type != 'company' && $tracker->user_id != Auth::user()->id) {
            return $this->error('Permission denied.', 403);
        }

        $images = TrackPhoto::where('track_id', $trackerId)
            ->where('created_by', Auth::user()->creatorId())
            ->get();

        return $this->success(['images' => $images], 'Tracker images retrieved.');
    }

    public function removeTrackerImage($imageId)
    {
        $image = TrackPhoto::where('id', $imageId)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$image) {
            return $this->error('Image not found.', 404);
        }

        // Check permissions
        $tracker = TimeTracker::where('id', $image->track_id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$tracker || (Auth::user()->type != 'company' && $tracker->user_id != Auth::user()->id)) {
            return $this->error('Permission denied.', 403);
        }

        $url = $image->img_path;
        $image->delete();
        Storage::delete($url);

        return $this->success([], 'Tracker image removed successfully.');
    }
}
