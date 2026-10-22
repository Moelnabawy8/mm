<?php

namespace App\Http\Controllers;

use App\Models\GoogleMeet;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class GoogleMeetController extends Controller
{
    public function index()
    {
        // Fetch meetings where user is creator OR a member
        $user_id = Auth::user()->id;
        $meetings = GoogleMeet::where('created_by', $user_id)
            ->orWhereJsonContains('meet_members', (string) $user_id)
            ->orderBy('start_date', 'desc')
            ->get();

        return view('google_meet.index', compact('meetings'));
    }

    public function create()
    {
        $users = User::where('created_by', Auth::user()->creatorId())->get();
        return view('google_meet.create', compact('users'));
    }

    public function store(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'meet_name' => 'required',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'members' => 'required|array',
            'meet_link' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->errors()->first());
        }

        GoogleMeet::create([
            'meet_name' => $request->meet_name,
            'created_by' => Auth::user()->id,
            'meet_members' => $request->members,
            'start_date' => \Carbon\Carbon::parse($request->start_date),
            'end_date' => \Carbon\Carbon::parse($request->end_date),
            'meet_link' => $request->meet_link,
            'status' => 'active'
        ]);

        return redirect()->route('google-meet.index')->with('success', __('Meeting created successfully.'));
    }

    public function edit($id)
    {
        $meet = GoogleMeet::findOrFail($id);
        $users = User::where('created_by', Auth::user()->creatorId())->get();
        return view('google_meet.edit', compact('meet', 'users'));
    }


    public function update(Request $request, $id)
    {
        $validator = \Validator::make($request->all(), [
            'meet_name' => 'required',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'members' => 'required|array',
            'meet_link' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->errors()->first());
        }

        $meet = GoogleMeet::find($id);
        $meet->update([
            'meet_name' => $request->meet_name,
            'meet_members' => $request->members,
            'start_date' => \Carbon\Carbon::parse($request->start_date),
            'end_date' => \Carbon\Carbon::parse($request->end_date),
            'meet_link' => $request->meet_link
        ]);

        return redirect()->route('google-meet.index')->with('success', __('Meeting updated successfully.'));
    }

    public function destroy($id)
    {
        $meet = GoogleMeet::find($id);
        if ($meet->created_by == Auth::user()->id) {
            $meet->delete();
            return redirect()->route('google-meet.index')->with('success', __('Meeting deleted.'));
        }
        return redirect()->back()->with('error', __('Permission denied.'));
    }

    public function finish($id)
    {
        $meet = GoogleMeet::find($id);
        if ($meet->created_by == Auth::user()->id) {
            $meet->status = 'finished';
            $meet->save();
            return redirect()->route('google-meet.index')->with('success', __('Meeting finished.'));
        }
        return redirect()->back()->with('error', __('Permission denied.'));
    }
}