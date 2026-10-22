<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Indicator;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\PerformanceType;
use App\Models\Employee;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class IndicatorApiController extends Controller
{
    use ApiResponser;

    public function getIndicators()
    {
        $user = Auth::user();

        if ($user->type == 'Employee') {
            $employee = Employee::where('user_id', $user->id)->first();
            $indicators = Indicator::where('created_by', '=', $user->creatorId())
                ->where('branch', $employee->branch_id)
                ->where('department', $employee->department_id)
                ->where('designation', $employee->designation_id)
                ->with(['branches', 'departments', 'designations', 'user'])
                ->get();
        } else {
            $indicators = Indicator::where('created_by', '=', $user->creatorId())
                ->with(['branches', 'departments', 'designations', 'user'])
                ->get();
        }

        return $this->success(['indicators' => $indicators], 'Indicators retrieved.');
    }

    public function createIndicator(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'branch' => 'required|exists:branches,id',
            'department' => 'required|exists:departments,id',
            'designation' => 'required|exists:designations,id',
            'rating' => 'required|array',
            'rating.*' => 'required|integer|min:1|max:5',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Check if branch belongs to user's company
        $branch = Branch::where('id', $request->branch)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$branch) {
            return $this->error('Branch not found or access denied.', 404);
        }

        // Check if department belongs to user's company
        $department = Department::where('id', $request->department)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$department) {
            return $this->error('Department not found or access denied.', 404);
        }

        // Check if designation belongs to user's company
        $designation = Designation::where('id', $request->designation)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$designation) {
            return $this->error('Designation not found or access denied.', 404);
        }

        $indicator = new Indicator();
        $indicator->branch = $request->branch;
        $indicator->department = $request->department;
        $indicator->designation = $request->designation;
        $indicator->rating = json_encode($request->rating, true);

        if (Auth::user()->type == 'company') {
            $indicator->created_user = Auth::user()->creatorId();
        } else {
            $indicator->created_user = Auth::user()->id;
        }

        $indicator->created_by = Auth::user()->creatorId();
        $indicator->save();

        return $this->success(['indicator' => $indicator], 'Indicator created successfully.');
    }

    public function getIndicator($id)
    {
        $indicator = Indicator::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with(['branches', 'departments', 'designations', 'user'])
            ->first();

        if (!$indicator) {
            return $this->error('Indicator not found.', 404);
        }

        $ratings = json_decode($indicator->rating, true);

        return $this->success(['indicator' => $indicator, 'ratings' => $ratings], 'Indicator detail.');
    }

    public function updateIndicator(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'branch' => 'required|exists:branches,id',
            'department' => 'required|exists:departments,id',
            'designation' => 'required|exists:designations,id',
            'rating' => 'required|array',
            'rating.*' => 'required|integer|min:1|max:5',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $indicator = Indicator::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$indicator) {
            return $this->error('Indicator not found.', 404);
        }

        // Check if branch belongs to user's company
        $branch = Branch::where('id', $request->branch)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$branch) {
            return $this->error('Branch not found or access denied.', 404);
        }

        // Check if department belongs to user's company
        $department = Department::where('id', $request->department)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$department) {
            return $this->error('Department not found or access denied.', 404);
        }

        // Check if designation belongs to user's company
        $designation = Designation::where('id', $request->designation)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$designation) {
            return $this->error('Designation not found or access denied.', 404);
        }

        $indicator->branch = $request->branch;
        $indicator->department = $request->department;
        $indicator->designation = $request->designation;
        $indicator->rating = json_encode($request->rating, true);
        $indicator->save();

        return $this->success(['indicator' => $indicator], 'Indicator updated successfully.');
    }

    public function deleteIndicator($id)
    {
        $indicator = Indicator::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$indicator) {
            return $this->error('Indicator not found.', 404);
        }

        $indicator->delete();

        return $this->success([], 'Indicator deleted successfully.');
    }

    public function getBranches()
    {
        $branches = Branch::where('created_by', Auth::user()->creatorId())
            ->get(['id', 'name']);

        return $this->success(['branches' => $branches], 'Branches retrieved.');
    }

    public function getDepartments()
    {
        $departments = Department::where('created_by', Auth::user()->creatorId())
            ->get(['id', 'name']);

        return $this->success(['departments' => $departments], 'Departments retrieved.');
    }

    public function getDesignations()
    {
        $designations = Designation::where('created_by', Auth::user()->creatorId())
            ->get(['id', 'name']);

        return $this->success(['designations' => $designations], 'Designations retrieved.');
    }

    public function getPerformanceTypes()
    {
        $performanceTypes = PerformanceType::where('created_by', Auth::user()->creatorId())
            ->get();

        return $this->success(['performance_types' => $performanceTypes], 'Performance types retrieved.');
    }

    public function getIndicatorsByBranch($branchId)
    {
        // Check if branch belongs to user's company
        $branch = Branch::where('id', $branchId)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$branch) {
            return $this->error('Branch not found or access denied.', 404);
        }

        $indicators = Indicator::where('created_by', Auth::user()->creatorId())
            ->where('branch', $branchId)
            ->with(['branches', 'departments', 'designations'])
            ->get();

        return $this->success(['indicators' => $indicators], 'Indicators by branch retrieved.');
    }

    public function getIndicatorsByDepartment($departmentId)
    {
        // Check if department belongs to user's company
        $department = Department::where('id', $departmentId)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$department) {
            return $this->error('Department not found or access denied.', 404);
        }

        $indicators = Indicator::where('created_by', Auth::user()->creatorId())
            ->where('department', $departmentId)
            ->with(['branches', 'departments', 'designations'])
            ->get();

        return $this->success(['indicators' => $indicators], 'Indicators by department retrieved.');
    }

    public function getIndicatorsByDesignation($designationId)
    {
        // Check if designation belongs to user's company
        $designation = Designation::where('id', $designationId)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$designation) {
            return $this->error('Designation not found or access denied.', 404);
        }

        $indicators = Indicator::where('created_by', Auth::user()->creatorId())
            ->where('designation', $designationId)
            ->with(['branches', 'departments', 'designations'])
            ->get();

        return $this->success(['indicators' => $indicators], 'Indicators by designation retrieved.');
    }

    public function getMyIndicators()
    {
        if (Auth::user()->type != 'Employee') {
            return $this->error('This endpoint is for employees only.', 403);
        }

        $employee = Employee::where('user_id', Auth::user()->id)->first();

        $indicators = Indicator::where('created_by', Auth::user()->creatorId())
            ->where('branch', $employee->branch_id)
            ->where('department', $employee->department_id)
            ->where('designation', $employee->designation_id)
            ->with(['branches', 'departments', 'designations'])
            ->get();

        return $this->success(['indicators' => $indicators], 'My indicators retrieved.');
    }

    public function getIndicatorStats()
    {
        $totalIndicators = Indicator::where('created_by', Auth::user()->creatorId())->count();

        // Group by branch
        $indicatorsByBranch = Indicator::where('created_by', Auth::user()->creatorId())
            ->with('branches')
            ->get()
            ->groupBy('branches.name')
            ->map(function ($group) {
                return $group->count();
            });

        // Group by department
        $indicatorsByDepartment = Indicator::where('created_by', Auth::user()->creatorId())
            ->with('departments')
            ->get()
            ->groupBy('departments.name')
            ->map(function ($group) {
                return $group->count();
            });

        // Group by designation
        $indicatorsByDesignation = Indicator::where('created_by', Auth::user()->creatorId())
            ->with('designations')
            ->get()
            ->groupBy('designations.name')
            ->map(function ($group) {
                return $group->count();
            });

        return $this->success([
            'total_indicators' => $totalIndicators,
            'indicators_by_branch' => $indicatorsByBranch,
            'indicators_by_department' => $indicatorsByDepartment,
            'indicators_by_designation' => $indicatorsByDesignation
        ], 'Indicator statistics retrieved.');
    }
}
