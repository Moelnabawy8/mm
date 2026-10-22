<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Shift;
use Illuminate\Http\Request;

class EmployeeShiftController extends Controller
{
    public function index()
    {
        $employeeShifts = EmployeeShift::with(['employee', 'shift'])
            ->where('created_by', \Auth::user()->creatorId())
            ->get();

        return view('employeeshift.index', compact('employeeShifts'));
    }

    public function create()
    {
        $employees = Employee::where('created_by', \Auth::user()->creatorId())
            ->get()->pluck('name', 'id');
        $shifts = Shift::where('created_by', \Auth::user()->creatorId())
            ->where('status', 'active')
            ->get()->pluck('name', 'id');

        return view('employeeshift.create', compact('employees', 'shifts'));
    }

    public function store(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'employee_id' => 'required',
            'shift_id'    => 'required',
            'start_date'  => 'required|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->getMessageBag()->first());
        }

        EmployeeShift::create([
            'employee_id' => $request->employee_id,
            'shift_id'    => $request->shift_id,
            'start_date'  => $request->start_date,
            'end_date'    => $request->end_date ?: null,
            'created_by'  => \Auth::user()->creatorId(),
        ]);

        return redirect()->route('employee-shift.index')->with('success', __('Shift allocated successfully.'));
    }

    public function edit(EmployeeShift $employeeShift)
    {
        if ($employeeShift->created_by != \Auth::user()->creatorId()) {
            return response()->json(['error' => __('Permission denied.')], 401);
        }

        $employees = Employee::where('created_by', \Auth::user()->creatorId())
            ->get()->pluck('name', 'id');
        $shifts = Shift::where('created_by', \Auth::user()->creatorId())
            ->where('status', 'active')
            ->get()->pluck('name', 'id');

        return view('employeeshift.edit', compact('employeeShift', 'employees', 'shifts'));
    }

    public function update(Request $request, EmployeeShift $employeeShift)
    {
        if ($employeeShift->created_by != \Auth::user()->creatorId()) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $validator = \Validator::make($request->all(), [
            'employee_id' => 'required',
            'shift_id'    => 'required',
            'start_date'  => 'required|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->getMessageBag()->first());
        }

        $employeeShift->update([
            'employee_id' => $request->employee_id,
            'shift_id'    => $request->shift_id,
            'start_date'  => $request->start_date,
            'end_date'    => $request->end_date ?: null,
        ]);

        return redirect()->route('employee-shift.index')->with('success', __('Allocation updated successfully.'));
    }

    public function destroy(EmployeeShift $employeeShift)
    {
        if ($employeeShift->created_by != \Auth::user()->creatorId()) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $employeeShift->delete();
        return redirect()->route('employee-shift.index')->with('success', __('Allocation removed successfully.'));
    }
}
