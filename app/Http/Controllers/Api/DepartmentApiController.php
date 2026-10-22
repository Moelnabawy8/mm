<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Department;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Designation;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class DepartmentApiController extends Controller
{
    use ApiResponser;

    public function getDepartments()
    {
        $departments = Department::where('created_by', Auth::user()->creatorId())->with(['branch'])->get();
        return $this->success(['departments' => $departments], 'Departments retrieved.');
    }

    public function createDepartment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required',
            'name' => 'required|max:190',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $department = new Department();
        $department->branch_id = $request->branch_id;
        $department->name = $request->name;
        $department->created_by = Auth::user()->creatorId();
        $department->save();

        return $this->success(['department' => $department], 'Department created successfully.');
    }

    public function getDepartment($id)
    {
        $department = Department::where('id', $id)->where('created_by', Auth::user()->creatorId())->with(['branch'])->first();
        if (!$department) {
            return $this->error('Department not found.', 404);
        }
        return $this->success(['department' => $department], 'Department detail.');
    }

    public function updateDepartment(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required',
            'name' => 'required|max:190',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $department = Department::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$department) {
            return $this->error('Department not found.', 404);
        }

        $department->branch_id = $request->branch_id;
        $department->name = $request->name;
        $department->save();

        return $this->success(['department' => $department], 'Department updated successfully.');
    }

    public function deleteDepartment($id)
    {
        $department = Department::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$department) {
            return $this->error('Department not found.', 404);
        }

        $employee = Employee::where('department_id', $department->id)->get();
        if (count($employee) > 0) {
            return $this->error('This department has employees. Please remove the employee from this department.', 400);
        }

        Designation::where('department_id', $department->id)->delete();
        $department->delete();

        return $this->success([], 'Department deleted successfully.');
    }

    public function getDepartmentEmployees($id)
    {
        $department = Department::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$department) {
            return $this->error('Department not found.', 404);
        }

        $employees = Employee::where('department_id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with(['user', 'department', 'designation', 'branch'])
            ->get();

        return $this->success(['employees' => $employees], 'Department employees retrieved.');
    }

    public function getBranches()
    {
        $branches = Branch::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['branches' => $branches], 'Branches retrieved.');
    }
}
