<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function index()
    {
        $shifts = Shift::where('created_by', \Auth::user()->creatorId())->get();
        return view('shift.index', compact('shifts'));
    }

    public function create()
    {
        return view('shift.create');
    }

    public function store(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'name'       => 'required|max:100',
            'start_time' => 'required',
            'end_time'   => 'required',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->getMessageBag()->first());
        }

        $workingDays = $request->working_days ? implode(',', $request->working_days) : null;

        Shift::create([
            'name'           => $request->name,
            'start_time'     => $request->start_time,
            'end_time'       => $request->end_time,
            'break_duration' => $request->break_duration ?? 0,
            'working_days'   => $workingDays,
            'status'         => $request->status ?? 'active',
            'created_by'     => \Auth::user()->creatorId(),
        ]);

        return redirect()->route('shift.index')->with('success', __('Shift created successfully.'));
    }

    public function edit(Shift $shift)
    {
        if ($shift->created_by != \Auth::user()->creatorId()) {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
        return view('shift.edit', compact('shift'));
    }

    public function update(Request $request, Shift $shift)
    {
        if ($shift->created_by != \Auth::user()->creatorId()) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $validator = \Validator::make($request->all(), [
            'name'       => 'required|max:100',
            'start_time' => 'required',
            'end_time'   => 'required',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->getMessageBag()->first());
        }

        $workingDays = $request->working_days ? implode(',', $request->working_days) : null;

        $shift->update([
            'name'           => $request->name,
            'start_time'     => $request->start_time,
            'end_time'       => $request->end_time,
            'break_duration' => $request->break_duration ?? 0,
            'working_days'   => $workingDays,
            'status'         => $request->status ?? 'active',
        ]);

        return redirect()->route('shift.index')->with('success', __('Shift updated successfully.'));
    }

    public function destroy(Shift $shift)
    {
        if ($shift->created_by != \Auth::user()->creatorId()) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $shift->delete();
        return redirect()->route('shift.index')->with('success', __('Shift deleted successfully.'));
    }
}
