<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Transfer;
use App\Models\Employee;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Utility;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TransferApiController extends Controller
{
    use ApiResponser;

    public function getTransfers()
    {
        if (Auth::user()->type == 'Employee') {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            $transfers = Transfer::where('created_by', '=', Auth::user()->creatorId())->where('employee_id', '=', $emp->id)->with('employee', 'branch', 'department')->get();
        } else {
            $transfers = Transfer::where('created_by', '=', Auth::user()->creatorId())->with('employee', 'branch', 'department')->get();
        }
        return $this->success(['transfers' => $transfers], 'Transfers retrieved.');
    }

    public function createTransfer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required',
            'branch_id' => 'required',
            'department_id' => 'required',
            'transfer_date' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $transfer = new Transfer();
        $transfer->employee_id = $request->employee_id;
        $transfer->branch_id = $request->branch_id;
        $transfer->department_id = $request->department_id;
        $transfer->transfer_date = $request->transfer_date;
        $transfer->description = $request->description;
        $transfer->created_by = Auth::user()->creatorId();
        $transfer->save();

        // Send Email
        $settings = Utility::settings();
        if ($settings['transfer_sent'] == 1) {
            $employee = Employee::find($transfer->employee_id);
            $branch = Branch::find($transfer->branch_id);
            $department = Department::find($transfer->department_id);

            $transferArr = [
                'transfer_name' => $employee->name,
                'transfer_email' => $employee->email,
                'transfer_date' => $transfer->transfer_date,
                'transfer_department' => $department->name,
                'transfer_branch' => $branch->name,
                'transfer_description' => $transfer->description,
            ];

            $resp = Utility::sendEmailTemplate('transfer_sent', [$employee->id => $employee->email], $transferArr);
        }

        return $this->success(['transfer' => $transfer], 'Transfer created successfully.');
    }

    public function getTransfer($id)
    {
        $transfer = Transfer::where('id', $id)->where('created_by', Auth::user()->creatorId())->with('employee', 'branch', 'department')->first();
        if (!$transfer) {
            return $this->error('Transfer not found.', 404);
        }
        return $this->success(['transfer' => $transfer], 'Transfer detail.');
    }

    public function updateTransfer(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required',
            'branch_id' => 'required',
            'department_id' => 'required',
            'transfer_date' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $transfer = Transfer::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$transfer) {
            return $this->error('Transfer not found.', 404);
        }

        $transfer->employee_id = $request->employee_id;
        $transfer->branch_id = $request->branch_id;
        $transfer->department_id = $request->department_id;
        $transfer->transfer_date = $request->transfer_date;
        $transfer->description = $request->description;
        $transfer->save();

        return $this->success(['transfer' => $transfer], 'Transfer updated successfully.');
    }

    public function deleteTransfer($id)
    {
        $transfer = Transfer::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$transfer) {
            return $this->error('Transfer not found.', 404);
        }
        $transfer->delete();
        return $this->success([], 'Transfer deleted successfully.');
    }

    public function getEmployees()
    {
        $employees = Employee::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['employees' => $employees], 'Employees retrieved.');
    }

    public function getBranches()
    {
        $branches = Branch::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['branches' => $branches], 'Branches retrieved.');
    }

    public function getDepartments()
    {
        $departments = Department::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['departments' => $departments], 'Departments retrieved.');
    }
}
