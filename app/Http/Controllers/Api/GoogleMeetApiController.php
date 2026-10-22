<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GoogleMeet;
use App\Models\User;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class GoogleMeetApiController extends Controller
{
    use ApiResponser;

    public function getMeetings()
    {
        $user_id = Auth::user()->id;
        $meetings = GoogleMeet::where('created_by', $user_id)
            ->orWhereJsonContains('meet_members', (string) $user_id)
            ->orderBy('start_date', 'desc')
            ->get();

        return $this->success(['meetings' => $meetings], 'Google Meet meetings retrieved.');
    }

    public function createMeeting(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'meet_name'  => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
            'members'    => 'required|array',
            'meet_link'  => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $meet = GoogleMeet::create([
            'meet_name'    => $request->meet_name,
            'created_by'   => Auth::user()->id,
            'meet_members' => $request->members,
            'start_date'   => $request->start_date,
            'end_date'     => $request->end_date,
            'meet_link'    => $request->meet_link,
            'status'       => 'active',
        ]);

        return $this->success(['meeting' => $meet], 'Google Meet meeting created successfully.');
    }

    public function getMeeting($id)
    {
        $user_id = Auth::user()->id;
        $meet = GoogleMeet::where('id', $id)
            ->where(function ($q) use ($user_id) {
                $q->where('created_by', $user_id)
                  ->orWhereJsonContains('meet_members', (string) $user_id);
            })
            ->first();

        if (!$meet) {
            return $this->error('Meeting not found or access denied.', 404);
        }

        return $this->success(['meeting' => $meet], 'Meeting detail.');
    }

    public function updateMeeting(Request $request, $id)
    {
        $meet = GoogleMeet::where('id', $id)
            ->where('created_by', Auth::user()->id)
            ->first();

        if (!$meet) {
            return $this->error('Meeting not found or permission denied.', 404);
        }

        $validator = Validator::make($request->all(), [
            'meet_name'  => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
            'members'    => 'required|array',
            'meet_link'  => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $meet->update([
            'meet_name'    => $request->meet_name,
            'meet_members' => $request->members,
            'start_date'   => $request->start_date,
            'end_date'     => $request->end_date,
            'meet_link'    => $request->meet_link,
        ]);

        return $this->success(['meeting' => $meet], 'Meeting updated successfully.');
    }

    public function deleteMeeting($id)
    {
        $meet = GoogleMeet::where('id', $id)
            ->where('created_by', Auth::user()->id)
            ->first();

        if (!$meet) {
            return $this->error('Meeting not found or permission denied.', 404);
        }

        $meet->delete();

        return $this->success([], 'Meeting deleted successfully.');
    }

    public function finishMeeting($id)
    {
        $meet = GoogleMeet::where('id', $id)
            ->where('created_by', Auth::user()->id)
            ->first();

        if (!$meet) {
            return $this->error('Meeting not found or permission denied.', 404);
        }

        $meet->status = 'finished';
        $meet->save();

        return $this->success(['meeting' => $meet], 'Meeting marked as finished.');
    }
}
