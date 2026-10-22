<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeClearance;
use Illuminate\Http\Request;

class EmployeeClearanceController extends Controller
{
    public function index()
    {
        if (\Auth::user()->can('manage termination')) // Re-using termination permission or we can create new one. Using termination for now as per "HR Admin Setup" context usually implies similar access.
        {
            if (\Auth::user()->type == 'Employee') {
                $emp = Employee::where('user_id', '=', \Auth::user()->id)->first();
                $clearances = EmployeeClearance::where('created_by', '=', \Auth::user()->creatorId())->where('employee_id', '=', $emp->id)->with(['employee'])->get();
            } else {
                $clearances = EmployeeClearance::where('created_by', '=', \Auth::user()->creatorId())->with(['employee'])->get();
            }

            return view('employee_clearance.index', compact('clearances'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function create()
    {
        if (\Auth::user()->can('create termination')) {
            $employees = Employee::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            return view('employee_clearance.create', compact('employees'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    public function store(Request $request)
    {
        if (\Auth::user()->can('create termination')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'employee_id' => 'required',
                ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $clearance = new EmployeeClearance();
            $clearance->employee_id = $request->employee_id;
            $clearance->is_cleared = $request->is_cleared ? 1 : 0;
            $clearance->description = $request->description;
            $clearance->created_by = \Auth::user()->creatorId();
            $clearance->save();

            return redirect()->route('employee-clearance.index')->with('success', __('Employee Clearance successfully created.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function edit($id)
    {
        if (\Auth::user()->can('edit termination')) {
            $employeeClearance = EmployeeClearance::find($id);
            $employees = Employee::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            if ($employeeClearance->created_by == \Auth::user()->creatorId()) {
                return view('employee_clearance.edit', compact('employeeClearance', 'employees'));
            } else {
                return response()->json(['error' => __('Permission denied.')], 401);
            }
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    public function update(Request $request, $id)
    {
        if (\Auth::user()->can('edit termination')) {
            $employeeClearance = EmployeeClearance::find($id);
            if ($employeeClearance->created_by == \Auth::user()->creatorId()) {
                $validator = \Validator::make(
                    $request->all(),
                    [
                        'employee_id' => 'required',
                    ]
                );

                if ($validator->fails()) {
                    $messages = $validator->getMessageBag();
                    return redirect()->back()->with('error', $messages->first());
                }

                $employeeClearance->employee_id = $request->employee_id;
                $employeeClearance->is_cleared = $request->is_cleared ? 1 : 0;
                $employeeClearance->description = $request->description;
                $employeeClearance->save();

                return redirect()->route('employee-clearance.index')->with('success', __('Employee Clearance successfully updated.'));
            } else {
                return redirect()->back()->with('error', __('Permission denied.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function destroy($id)
    {
        if (\Auth::user()->can('delete termination')) {
            $employeeClearance = EmployeeClearance::find($id);
            if ($employeeClearance->created_by == \Auth::user()->creatorId()) {
                $employeeClearance->delete();
                return redirect()->route('employee-clearance.index')->with('success', __('Employee Clearance successfully deleted.'));
            } else {
                return redirect()->back()->with('error', __('Permission denied.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
}
