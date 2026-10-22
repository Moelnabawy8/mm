<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Designation;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class DesignationApiController extends Controller
{
    use ApiResponser;

    public function getDesignations()
    {
        $designations = Designation::where('created_by', Auth::user()->creatorId())
            ->with(['branch', 'department'])
            ->orderBy('name', 'asc')
            ->get();

        return $this->success(['designations' => $designations], 'Designations retrieved.');
    }

    public function createDesignation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'department_id' => 'required|exists:departments,id',
            'name' => 'required|string|max:190',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Check if branch belongs to user's company
        $branch = Branch::where('id', $request->branch_id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$branch) {
            return $this->error('Branch not found or access denied.', 404);
        }

        // Check if department belongs to user's company
        $department = Department::where('id', $request->department_id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$department) {
            return $this->error('Department not found or access denied.', 404);
        }

        // Check if designation name already exists for the same department
        $existingDesignation = Designation::where('name', $request->name)
            ->where('department_id', $request->department_id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if ($existingDesignation) {
            return $this->error('Designation with this name already exists in the department.', 400);
        }

        $designation = new Designation();
        $designation->branch_id = $request->branch_id;
        $designation->department_id = $request->department_id;
        $designation->name = $request->name;
        $designation->created_by = Auth::user()->creatorId();
        $designation->save();

        return $this->success(['designation' => $designation], 'Designation created successfully.');
    }

    public function getDesignation($id)
    {
        $designation = Designation::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with(['branch', 'department'])
            ->first();

        if (!$designation) {
            return $this->error('Designation not found.', 404);
        }

        return $this->success(['designation' => $designation], 'Designation detail.');
    }

    public function updateDesignation(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'department_id' => 'required|exists:departments,id',
            'name' => 'required|string|max:190',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $designation = Designation::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$designation) {
            return $this->error('Designation not found.', 404);
        }

        // Check if branch belongs to user's company
        $branch = Branch::where('id', $request->branch_id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$branch) {
            return $this->error('Branch not found or access denied.', 404);
        }

        // Check if department belongs to user's company
        $department = Department::where('id', $request->department_id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$department) {
            return $this->error('Department not found or access denied.', 404);
        }

        // Check if designation name already exists for the same department (excluding current designation)
        $existingDesignation = Designation::where('name', $request->name)
            ->where('department_id', $request->department_id)
            ->where('id', '!=', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if ($existingDesignation) {
            return $this->error('Designation with this name already exists in the department.', 400);
        }

        // Update branch based on department
        try {
            $branchId = Department::where('id', $request->department_id)
                ->where('created_by', Auth::user()->creatorId())
                ->first()
                ->branch->id;
        } catch (\Exception $e) {
            $branchId = $request->branch_id;
        }

        $designation->name = $request->name;
        $designation->branch_id = $branchId;
        $designation->department_id = $request->department_id;
        $designation->save();

        return $this->success(['designation' => $designation], 'Designation updated successfully.');
    }

    public function deleteDesignation($id)
    {
        $designation = Designation::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$designation) {
            return $this->error('Designation not found.', 404);
        }

        // Check if designation has employees
        $employees = Employee::where('designation_id', $designation->id)->get();
        if ($employees->count() > 0) {
            return $this->error('This designation has employees. Please remove the employees from this designation first.', 400);
        }

        $designation->delete();

        return $this->success([], 'Designation deleted successfully.');
    }

    public function getBranches()
    {
        $branches = Branch::where('created_by', Auth::user()->creatorId())
            ->get(['id', 'name']);

        return $this->success(['branches' => $branches], 'Branches retrieved.');
    }

    public function getDepartments($branchId = null)
    {
        $query = Department::where('created_by', Auth::user()->creatorId());

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $departments = $query->get(['id', 'name']);

        return $this->success(['departments' => $departments], 'Departments retrieved.');
    }

    public function getDesignationsByDepartment($departmentId)
    {
        // Check if department belongs to user's company
        $department = Department::where('id', $departmentId)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$department) {
            return $this->error('Department not found or access denied.', 404);
        }

        $designations = Designation::where('department_id', $departmentId)
            ->where('created_by', Auth::user()->creatorId())
            ->orderBy('name', 'asc')
            ->get(['id', 'name']);

        return $this->success(['designations' => $designations], 'Designations by department retrieved.');
    }

    public function getDesignationsByBranch($branchId)
    {
        // Check if branch belongs to user's company
        $branch = Branch::where('id', $branchId)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$branch) {
            return $this->error('Branch not found or access denied.', 404);
        }

        $designations = Designation::where('branch_id', $branchId)
            ->where('created_by', Auth::user()->creatorId())
            ->with(['department'])
            ->orderBy('name', 'asc')
            ->get();

        return $this->success(['designations' => $designations], 'Designations by branch retrieved.');
    }

    public function getDesignationStats()
    {
        $totalDesignations = Designation::where('created_by', Auth::user()->creatorId())->count();

        // Group by department
        $designationsByDepartment = Designation::where('created_by', Auth::user()->creatorId())
            ->with('department')
            ->get()
            ->groupBy('department.name')
            ->map(function ($group) {
                return $group->count();
            });

        // Group by branch
        $designationsByBranch = Designation::where('created_by', Auth::user()->creatorId())
            ->with('branch')
            ->get()
            ->groupBy('branch.name')
            ->map(function ($group) {
                return $group->count();
            });

        return $this->success([
            'total_designations' => $totalDesignations,
            'designations_by_department' => $designationsByDepartment,
            'designations_by_branch' => $designationsByBranch
        ], 'Designation statistics retrieved.');
    }
}
