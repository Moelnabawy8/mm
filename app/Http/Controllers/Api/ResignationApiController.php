<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Resignation;
use App\Models\Employee;
use App\Models\Utility;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ResignationApiController extends Controller
{
    use ApiResponser;

    public function getResignations()
    {
        if (Auth::user()->type == 'Employee') {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            $resignations = Resignation::where('created_by', '=', Auth::user()->creatorId())
                ->where('employee_id', '=', $emp->id)
                ->with('employee')
                ->get();
        } else {
            $resignations = Resignation::where('created_by', '=', Auth::user()->creatorId())
                ->with('employee')
                ->get();
        }

        return $this->success(['resignations' => $resignations], 'Resignations retrieved.');
    }

    public function createResignation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'notice_date' => 'required|date',
            'resignation_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $resignation = new Resignation();
        $user = Auth::user();

        if ($user->type == 'Employee') {
            $employee = Employee::where('user_id', $user->id)->first();
            if (!$employee) {
                return $this->error('Employee record not found.', 404);
            }
            $resignation->employee_id = $employee->id;
        } else {
            // For company/HR users, employee_id is required
            $validator = Validator::make($request->all(), [
                'employee_id' => 'required|exists:employees,id',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), 422);
            }

            // Check if employee belongs to user's company
            $employee = Employee::where('id', $request->employee_id)
                ->where('created_by', Auth::user()->creatorId())
                ->first();

            if (!$employee) {
                return $this->error('Employee not found or access denied.', 404);
            }

            $resignation->employee_id = $request->employee_id;
        }

        $resignation->notice_date = $request->notice_date;
        $resignation->resignation_date = $request->resignation_date;
        $resignation->description = $request->description;
        $resignation->created_by = Auth::user()->creatorId();
        $resignation->save();

        // Send Email
        $settings = Utility::settings();
        if ($settings['resignation_sent'] == 1) {
            $employee = Employee::find($resignation->employee_id);

            $resignationArr = [
                'resignation_email' => $employee->email,
                'assign_user' => $employee->name,
                'resignation_date' => $resignation->resignation_date,
                'notice_date' => $resignation->notice_date,
            ];

            $resp = Utility::sendEmailTemplate('resignation_sent', [$employee->email], $resignationArr);
        }

        return $this->success(['resignation' => $resignation], 'Resignation created successfully.');
    }

    public function getResignation($id)
    {
        $resignation = Resignation::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with('employee')
            ->first();

        if (!$resignation) {
            return $this->error('Resignation not found.', 404);
        }

        return $this->success(['resignation' => $resignation], 'Resignation detail.');
    }

    public function updateResignation(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'notice_date' => 'required|date',
            'resignation_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $resignation = Resignation::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$resignation) {
            return $this->error('Resignation not found.', 404);
        }

        // Only non-employee users can change employee_id
        if (Auth::user()->type != 'employee') {
            if ($request->has('employee_id')) {
                $validator = Validator::make($request->all(), [
                    'employee_id' => 'required|exists:employees,id',
                ]);

                if ($validator->fails()) {
                    return $this->error($validator->errors()->first(), 422);
                }

                // Check if employee belongs to user's company
                $employee = Employee::where('id', $request->employee_id)
                    ->where('created_by', Auth::user()->creatorId())
                    ->first();

                if (!$employee) {
                    return $this->error('Employee not found or access denied.', 404);
                }

                $resignation->employee_id = $request->employee_id;
            }
        }

        $resignation->notice_date = $request->notice_date;
        $resignation->resignation_date = $request->resignation_date;
        $resignation->description = $request->description;
        $resignation->save();

        return $this->success(['resignation' => $resignation], 'Resignation updated successfully.');
    }

    public function deleteResignation($id)
    {
        $resignation = Resignation::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$resignation) {
            return $this->error('Resignation not found.', 404);
        }

        $resignation->delete();

        return $this->success([], 'Resignation deleted successfully.');
    }

    public function getEmployees()
    {
        $user = Auth::user();

        if ($user->type == 'company' || $user->type == 'HR') {
            $employees = Employee::where('created_by', Auth::user()->creatorId())->get();
        } else {
            // Employee users can only see themselves
            $employees = Employee::where('user_id', Auth::user()->id)->get();
        }

        return $this->success(['employees' => $employees], 'Employees retrieved.');
    }
}
