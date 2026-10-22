<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Termination;
use App\Models\Employee;
use App\Models\TerminationType;
use App\Models\Utility;
use App\Models\EmployeeClearance;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TerminationApiController extends Controller
{
    use ApiResponser;

    public function getTerminations()
    {
        if (Auth::user()->type == 'Employee') {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            $terminations = Termination::where('created_by', '=', Auth::user()->creatorId())
                ->where('employee_id', '=', $emp->id)
                ->with(['terminationType', 'employee', 'employee.clearance'])
                ->get();
        } else {
            $terminations = Termination::where('created_by', '=', Auth::user()->creatorId())
                ->with(['terminationType', 'employee', 'employee.clearance'])
                ->get();
        }

        return $this->success(['terminations' => $terminations], 'Terminations retrieved.');
    }

    public function createTermination(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required',
            'termination_type' => 'required',
            'notice_date' => 'required|date',
            'termination_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Check if employee exists and belongs to user's company
        $employee = Employee::where('id', $request->employee_id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$employee) {
            return $this->error('Employee not found or access denied.', 404);
        }

        // Check if termination type exists and belongs to user's company
        $terminationType = TerminationType::where('id', $request->termination_type)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$terminationType) {
            return $this->error('Termination type not found or access denied.', 404);
        }

        $termination = new Termination();
        $termination->employee_id = $request->employee_id;
        $termination->termination_type = $request->termination_type;
        $termination->notice_date = $request->notice_date;
        $termination->termination_date = $request->termination_date;
        $termination->description = $request->description;
        $termination->created_by = Auth::user()->creatorId();
        $termination->save();

        // Send Email
        $settings = Utility::settings();
        if ($settings['termination_sent'] == 1) {
            $terminationArr = [
                'termination_name' => $employee->name,
                'termination_email' => $employee->email,
                'notice_date' => $termination->notice_date,
                'termination_date' => $termination->termination_date,
                'termination_type' => $terminationType->name,
            ];

            $resp = Utility::sendEmailTemplate('termination_sent', [$employee->id => $employee->email], $terminationArr);
        }

        return $this->success(['termination' => $termination], 'Termination created successfully.');
    }

    public function getTermination($id)
    {
        $termination = Termination::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with(['terminationType', 'employee', 'employee.clearance'])
            ->first();

        if (!$termination) {
            return $this->error('Termination not found.', 404);
        }

        return $this->success(['termination' => $termination], 'Termination detail.');
    }

    public function updateTermination(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required',
            'termination_type' => 'required',
            'notice_date' => 'required|date',
            'termination_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $termination = Termination::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$termination) {
            return $this->error('Termination not found.', 404);
        }

        // Check if employee exists and belongs to user's company
        $employee = Employee::where('id', $request->employee_id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$employee) {
            return $this->error('Employee not found or access denied.', 404);
        }

        // Check if termination type exists and belongs to user's company
        $terminationType = TerminationType::where('id', $request->termination_type)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$terminationType) {
            return $this->error('Termination type not found or access denied.', 404);
        }

        $termination->employee_id = $request->employee_id;
        $termination->termination_type = $request->termination_type;
        $termination->notice_date = $request->notice_date;
        $termination->termination_date = $request->termination_date;
        $termination->description = $request->description;
        $termination->save();

        return $this->success(['termination' => $termination], 'Termination updated successfully.');
    }

    public function deleteTermination($id)
    {
        $termination = Termination::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$termination) {
            return $this->error('Termination not found.', 404);
        }

        $termination->delete();

        return $this->success([], 'Termination deleted successfully.');
    }

    public function getEmployees()
    {
        $employees = Employee::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['employees' => $employees], 'Employees retrieved.');
    }

    public function getTerminationTypes()
    {
        $terminationTypes = TerminationType::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['termination_types' => $terminationTypes], 'Termination types retrieved.');
    }

    public function getClearedEmployees()
    {
        // Get IDs of employees who are cleared
        $clearedEmployees = EmployeeClearance::where('created_by', Auth::user()->creatorId())
            ->where('is_cleared', 1)
            ->pluck('employee_id')
            ->toArray();

        $employees = Employee::whereIn('id', $clearedEmployees)
            ->where('created_by', Auth::user()->creatorId())
            ->get();

        return $this->success(['cleared_employees' => $employees], 'Cleared employees retrieved.');
    }

    public function getTerminationDescription($id)
    {
        $termination = Termination::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$termination) {
            return $this->error('Termination not found.', 404);
        }

        return $this->success(['description' => $termination->description], 'Termination description retrieved.');
    }
}
