<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Warning;
use App\Models\Employee;
use App\Models\Utility;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class WarningApiController extends Controller
{
    use ApiResponser;

    public function getWarnings()
    {
        if (Auth::user()->type == 'Employee') {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            $warnings = Warning::where('warning_by', '=', $emp->id)->with(['warningTo'])->get();
        } else {
            $warnings = Warning::where('created_by', '=', Auth::user()->creatorId())->with(['warningTo'])->get();
        }
        return $this->success(['warnings' => $warnings], 'Warnings retrieved.');
    }

    public function createWarning(Request $request)
    {
        if (Auth::user()->type != 'employee') {
            $validator = Validator::make($request->all(), [
                'warning_by' => 'required',
            ]);
        }

        $validator = Validator::make($request->all(), [
            'warning_to' => 'required',
            'subject' => 'required',
            'warning_date' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $warning = new Warning();
        if (Auth::user()->type == 'Employee') {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            $warning->warning_by = $emp->id;
        } else {
            $warning->warning_by = $request->warning_by;
        }
        $warning->warning_to = $request->warning_to;
        $warning->subject = $request->subject;
        $warning->warning_date = $request->warning_date;
        $warning->description = $request->description;
        $warning->created_by = Auth::user()->creatorId();
        $warning->save();

        // Send Email
        $settings = Utility::settings();
        if ($settings['warning_sent'] == 1) {
            $employee = Employee::find($warning->warning_to);
            $warningArr = [
                'employee_warning_name' => $employee->name,
                'warning_subject' => $warning->subject,
                'warning_description' => $warning->description,
            ];

            $resp = Utility::sendEmailTemplate('warning_sent', [$employee->id => $employee->email], $warningArr);
        }

        return $this->success(['warning' => $warning], 'Warning created successfully.');
    }

    public function getWarning($id)
    {
        $warning = Warning::where('id', $id)->where('created_by', Auth::user()->creatorId())->with(['warningTo', 'warningBy'])->first();
        if (!$warning) {
            return $this->error('Warning not found.', 404);
        }
        return $this->success(['warning' => $warning], 'Warning detail.');
    }

    public function updateWarning(Request $request, $id)
    {
        $warning = Warning::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$warning) {
            return $this->error('Warning not found.', 404);
        }

        if (Auth::user()->type != 'employee') {
            $validator = Validator::make($request->all(), [
                'warning_by' => 'required',
            ]);
        }

        $validator = Validator::make($request->all(), [
            'warning_to' => 'required',
            'subject' => 'required',
            'warning_date' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        if (Auth::user()->type == 'Employee') {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            $warning->warning_by = $emp->id;
        } else {
            $warning->warning_by = $request->warning_by;
        }

        $warning->warning_to = $request->warning_to;
        $warning->subject = $request->subject;
        $warning->warning_date = $request->warning_date;
        $warning->description = $request->description;
        $warning->save();

        return $this->success(['warning' => $warning], 'Warning updated successfully.');
    }

    public function deleteWarning($id)
    {
        $warning = Warning::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$warning) {
            return $this->error('Warning not found.', 404);
        }
        $warning->delete();
        return $this->success([], 'Warning deleted successfully.');
    }

    public function getEmployees()
    {
        if (Auth::user()->type == 'Employee') {
            $user = Auth::user();
            $current_employee = Employee::where('user_id', $user->id)->get()->pluck('name', 'id');
            $employees = Employee::where('user_id', '!=', $user->id)->get()->pluck('name', 'id');
        } else {
            $user = Auth::user();
            $current_employee = Employee::where('user_id', $user->id)->get()->pluck('name', 'id');
            $employees = Employee::where('created_by', Auth::user()->creatorId())->get()->pluck('name', 'id');
        }

        return $this->success([
            'current_employee' => $current_employee,
            'employees' => $employees
        ], 'Employees retrieved.');
    }
}
