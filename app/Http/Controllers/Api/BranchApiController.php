<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BranchApiController extends Controller
{
    use ApiResponser;

    public function getBranches()
    {
        $branches = Branch::where('created_by', Auth::user()->creatorId())
            ->withCount(['departments', 'designations', 'employees'])
            ->orderBy('name', 'asc')
            ->get()
            ->map(fn($b) => [
                'id'                  => $b->id,
                'name'                => $b->name,
                'departments_count'   => $b->departments_count,
                'designations_count'  => $b->designations_count,
                'employees_count'     => $b->employees_count,
                'created_by'          => $b->created_by,
            ]);

        return $this->success(['branches' => $branches], 'Branches retrieved.');
    }

    public function createBranch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Check if branch name already exists for this user
        $existingBranch = Branch::where('name', $request->name)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if ($existingBranch) {
            return $this->error('Branch with this name already exists.', 400);
        }

        $branch = new Branch();
        $branch->name = $request->name;
        $branch->created_by = Auth::user()->creatorId();
        $branch->save();

        return $this->success(['branch' => $branch], 'Branch created successfully.');
    }

    public function getBranch($id)
    {
        $branch = Branch::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with(['departments', 'designations', 'employees'])
            ->first();

        if (!$branch) {
            return $this->error('Branch not found.', 404);
        }

        return $this->success(['branch' => $branch], 'Branch detail.');
    }

    public function updateBranch(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $branch = Branch::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$branch) {
            return $this->error('Branch not found.', 404);
        }

        // Check if branch name already exists for this user (excluding current branch)
        $existingBranch = Branch::where('name', $request->name)
            ->where('created_by', Auth::user()->creatorId())
            ->where('id', '!=', $id)
            ->first();

        if ($existingBranch) {
            return $this->error('Branch with this name already exists.', 400);
        }

        $branch->name = $request->name;
        $branch->save();

        return $this->success(['branch' => $branch], 'Branch updated successfully.');
    }

    public function deleteBranch($id)
    {
        $branch = Branch::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$branch) {
            return $this->error('Branch not found.', 404);
        }

        // Check if branch has employees
        $employees = Employee::where('branch_id', $branch->id)->get();
        if ($employees->count() > 0) {
            return $this->error('Cannot delete branch with employees. Please remove the employees from this branch first.', 400);
        }

        // Check if branch has departments and handle cascade deletion
        $departments = Department::where('branch_id', $branch->id)->get();
        foreach ($departments as $department) {
            // Delete designations associated with these departments
            Designation::where('department_id', $department->id)->delete();
            $department->delete();
        }

        $branch->delete();

        return $this->success([], 'Branch deleted successfully.');
    }

    public function getDepartments($branchId = null)
    {
        if ($branchId == '0' || !$branchId) {
            $departments = Department::where('created_by', Auth::user()->creatorId())
                ->get(['id', 'name']);
        } else {
            // Check if branch belongs to user's company
            $branch = Branch::where('id', $branchId)
                ->where('created_by', Auth::user()->creatorId())
                ->first();

            if (!$branch) {
                return $this->error('Branch not found or access denied.', 404);
            }

            $departments = Department::where('branch_id', $branchId)
                ->get(['id', 'name']);
        }

        return $this->success(['departments' => $departments], 'Departments retrieved.');
    }

    public function getEmployees($departmentId = null)
    {
        if ($departmentId == '0' || !$departmentId) {
            $employees = Employee::where('created_by', Auth::user()->creatorId())
                ->get(['id', 'name']);
        } else {
            // Check if department belongs to user's company
            $department = Department::where('id', $departmentId)
                ->where('created_by', Auth::user()->creatorId())
                ->first();

            if (!$department) {
                return $this->error('Department not found or access denied.', 404);
            }

            $employees = Employee::where('department_id', $departmentId)
                ->get(['id', 'name']);
        }

        return $this->success(['employees' => $employees], 'Employees retrieved.');
    }

    public function getBranchStats()
    {
        $totalBranches = Branch::where('created_by', Auth::user()->creatorId())->count();

        // Get employee count per branch
        $branchesWithEmployeeCount = Branch::where('created_by', Auth::user()->creatorId())
            ->withCount('employees')
            ->get();

        $branchStats = [];
        foreach ($branchesWithEmployeeCount as $branch) {
            $branchStats[] = [
                'id' => $branch->id,
                'name' => $branch->name,
                'employee_count' => $branch->employees_count,
                'department_count' => Department::where('branch_id', $branch->id)->count(),
                'designation_count' => Designation::where('branch_id', $branch->id)->count(),
            ];
        }

        // Get total employees across all branches
        $totalEmployees = Employee::where('created_by', Auth::user()->creatorId())->count();
        $totalDepartments = Department::where('created_by', Auth::user()->creatorId())->count();
        $totalDesignations = Designation::where('created_by', Auth::user()->creatorId())->count();

        return $this->success([
            'total_branches' => $totalBranches,
            'total_employees' => $totalEmployees,
            'total_departments' => $totalDepartments,
            'total_designations' => $totalDesignations,
            'branch_stats' => $branchStats
        ], 'Branch statistics retrieved.');
    }

    public function getBranchWithDetails($id)
    {
        $branch = Branch::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with(['departments.designations', 'departments.employees'])
            ->first();

        if (!$branch) {
            return $this->error('Branch not found.', 404);
        }

        return $this->success(['branch' => $branch], 'Branch details retrieved.');
    }

    public function getBranchEmployees($id)
    {
        $branch = Branch::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$branch) {
            return $this->error('Branch not found.', 404);
        }

        $employees = Employee::where('branch_id', $id)
            ->with(['department', 'designation'])
            ->orderBy('name', 'asc')
            ->get();

        return $this->success(['employees' => $employees], 'Branch employees retrieved.');
    }

    public function getBranchDepartments($id)
    {
        $branch = Branch::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$branch) {
            return $this->error('Branch not found.', 404);
        }

        $departments = Department::where('branch_id', $id)
            ->with(['designations'])
            ->orderBy('name', 'asc')
            ->get();

        return $this->success(['departments' => $departments], 'Branch departments retrieved.');
    }

    public function getBranchDesignations($id)
    {
        $branch = Branch::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$branch) {
            return $this->error('Branch not found.', 404);
        }

        $designations = Designation::where('branch_id', $id)
            ->with(['department'])
            ->orderBy('name', 'asc')
            ->get();

        return $this->success(['designations' => $designations], 'Branch designations retrieved.');
    }
}
