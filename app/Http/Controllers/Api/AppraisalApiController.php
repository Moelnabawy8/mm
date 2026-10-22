<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Appraisal;
use App\Models\Branch;
use App\Models\Competencies;
use App\Models\Employee;
use App\Models\Indicator;
use App\Models\PerformanceType;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AppraisalApiController extends Controller
{
    use ApiResponser;

    public function getAppraisals()
    {
        $user = Auth::user();

        if ($user->type == 'Employee') {
            $employee = Employee::where('user_id', $user->id)->first();
            $competencyCount = Competencies::where('created_by', '=', $user->creatorId())->count();
            $appraisals = Appraisal::where('created_by', '=', Auth::user()->creatorId())
                ->where('branch', $employee->branch_id)
                ->where('employee', $employee->id)
                ->with(['employees', 'branches'])
                ->orderBy('appraisal_date', 'desc')
                ->get();
        } else {
            $competencyCount = Competencies::where('created_by', '=', $user->creatorId())->count();
            $appraisals = Appraisal::where('created_by', '=', Auth::user()->creatorId())
                ->with(['employees', 'branches'])
                ->orderBy('appraisal_date', 'desc')
                ->get();
        }

        return $this->success([
            'appraisals' => $appraisals,
            'competency_count' => $competencyCount
        ], 'Appraisals retrieved.');
    }

    public function createAppraisal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'branch' => 'required|exists:branches,id',
            'employee' => 'required|exists:employees,id',
            'appraisal_date' => 'required|date|date_format:Y-m-d',
            'rating' => 'required|array',
            'rating.*' => 'required|integer|min:1|max:5',
            'remark' => 'nullable|string',
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

        // Check if employee belongs to user's company
        $employee = Employee::where('id', $request->employee)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$employee) {
            return $this->error('Employee not found or access denied.', 404);
        }

        $appraisal = new Appraisal();
        $appraisal->branch = $request->branch;
        $appraisal->employee = $request->employee;
        $appraisal->appraisal_date = $request->appraisal_date;
        $appraisal->rating = json_encode($request->rating, true);
        $appraisal->remark = $request->remark;
        $appraisal->created_by = Auth::user()->creatorId();
        $appraisal->save();

        return $this->success(['appraisal' => $appraisal], 'Appraisal created successfully.');
    }

    public function getAppraisal($id)
    {
        $appraisal = Appraisal::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with(['employees', 'branches'])
            ->first();

        if (!$appraisal) {
            return $this->error('Appraisal not found.', 404);
        }

        // Check if employee has access to this appraisal
        if (Auth::user()->type == 'Employee') {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            if ($appraisal->employee != $emp->id) {
                return $this->error('Permission denied.', 403);
            }
        }

        // Decode rating for API response
        $appraisal->rating_decoded = json_decode($appraisal->rating, true);

        // Get performance types
        $performanceTypes = PerformanceType::where('created_by', '=', Auth::user()->creatorId())->get();

        // Get employee and indicator for rating comparison
        $employee = Employee::find($appraisal->employee);
        $indicator = null;
        $ratings = null;

        if ($employee != null) {
            $indicator = Indicator::where('branch', $employee->branch_id)
                ->where('department', $employee->department_id)
                ->where('designation', $employee->designation_id)
                ->first();

            if ($indicator != null) {
                $ratings = json_decode($indicator->rating, true);
            }
        }

        return $this->success([
            'appraisal' => $appraisal,
            'performance_types' => $performanceTypes,
            'indicator_ratings' => $ratings
        ], 'Appraisal detail retrieved.');
    }

    public function updateAppraisal(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'branch' => 'required|exists:branches,id',
            'employee' => 'required|exists:employees,id',
            'appraisal_date' => 'required|date|date_format:Y-m-d',
            'rating' => 'required|array',
            'rating.*' => 'required|integer|min:1|max:5',
            'remark' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $appraisal = Appraisal::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$appraisal) {
            return $this->error('Appraisal not found.', 404);
        }

        // Check if branch belongs to user's company
        $branch = Branch::where('id', $request->branch)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$branch) {
            return $this->error('Branch not found or access denied.', 404);
        }

        // Check if employee belongs to user's company
        $employee = Employee::where('id', $request->employee)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$employee) {
            return $this->error('Employee not found or access denied.', 404);
        }

        $appraisal->branch = $request->branch;
        $appraisal->employee = $request->employee;
        $appraisal->appraisal_date = $request->appraisal_date;
        $appraisal->rating = json_encode($request->rating, true);
        $appraisal->remark = $request->remark;
        $appraisal->save();

        return $this->success(['appraisal' => $appraisal], 'Appraisal updated successfully.');
    }

    public function deleteAppraisal($id)
    {
        $appraisal = Appraisal::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$appraisal) {
            return $this->error('Appraisal not found.', 404);
        }

        // Check if employee has access to delete this appraisal
        if (Auth::user()->type == 'Employee') {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            if ($appraisal->employee != $emp->id) {
                return $this->error('Permission denied.', 403);
            }
        }

        $appraisal->delete();

        return $this->success([], 'Appraisal deleted successfully.');
    }

    public function getBranches()
    {
        $branches = Branch::where('created_by', Auth::user()->creatorId())
            ->get(['id', 'name']);

        return $this->success(['branches' => $branches], 'Branches retrieved.');
    }

    public function getEmployees($branchId = null)
    {
        $query = Employee::where('created_by', Auth::user()->creatorId());

        if ($branchId) {
            // Check if branch belongs to user's company
            $branch = Branch::where('id', $branchId)
                ->where('created_by', Auth::user()->creatorId())
                ->first();

            if (!$branch) {
                return $this->error('Branch not found or access denied.', 404);
            }

            $query->where('branch_id', $branchId);
        }

        $employees = $query->get(['id', 'name']);

        return $this->success(['employees' => $employees], 'Employees retrieved.');
    }

    public function getPerformanceTypes()
    {
        $performanceTypes = PerformanceType::where('created_by', Auth::user()->creatorId())
            ->get();

        return $this->success(['performance_types' => $performanceTypes], 'Performance types retrieved.');
    }

    public function getEmployeeIndicators($employeeId)
    {
        // Check if employee belongs to user's company
        $employee = Employee::where('id', $employeeId)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$employee) {
            return $this->error('Employee not found or access denied.', 404);
        }

        $indicator = Indicator::where('branch', $employee->branch_id)
            ->where('department', $employee->department_id)
            ->where('designation', $employee->designation_id)
            ->first();

        $ratings = !empty($indicator) ? json_decode($indicator->rating, true) : [];

        $performanceTypes = PerformanceType::where('created_by', '=', Auth::user()->creatorId())->get();

        return $this->success([
            'ratings' => $ratings,
            'performance_types' => $performanceTypes
        ], 'Employee indicators retrieved.');
    }

    public function getAppraisalIndicators($appraisalId)
    {
        $appraisal = Appraisal::where('id', $appraisalId)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$appraisal) {
            return $this->error('Appraisal not found.', 404);
        }

        $employee = Employee::find($appraisal->employee);
        $indicator = null;
        $ratings = null;

        if ($employee != null) {
            $indicator = Indicator::where('branch', $employee->branch_id)
                ->where('department', $employee->department_id)
                ->where('designation', $employee->designation_id)
                ->first();

            if ($indicator != null) {
                $ratings = json_decode($indicator->rating, true);
            }
        }

        $rating = json_decode($appraisal->rating, true);
        $performanceTypes = PerformanceType::where('created_by', '=', Auth::user()->creatorId())->get();

        return $this->success([
            'indicator_ratings' => $ratings,
            'appraisal_rating' => $rating,
            'performance_types' => $performanceTypes
        ], 'Appraisal indicators retrieved.');
    }

    public function getMyAppraisals()
    {
        if (Auth::user()->type != 'Employee') {
            return $this->error('This endpoint is for employees only.', 403);
        }

        $employee = Employee::where('user_id', '=', Auth::user()->id)->first();
        $appraisals = Appraisal::where('created_by', '=', Auth::user()->creatorId())
            ->where('branch', $employee->branch_id)
            ->where('employee', $employee->id)
            ->with(['branches'])
            ->orderBy('appraisal_date', 'desc')
            ->get();

        return $this->success(['appraisals' => $appraisals], 'My appraisals retrieved.');
    }

    public function getAppraisalsByEmployee($employeeId)
    {
        // Check if employee belongs to user's company
        $employee = Employee::where('id', $employeeId)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$employee) {
            return $this->error('Employee not found or access denied.', 404);
        }

        $appraisals = Appraisal::where('created_by', Auth::user()->creatorId())
            ->where('employee', $employeeId)
            ->with(['branches'])
            ->orderBy('appraisal_date', 'desc')
            ->get();

        return $this->success(['appraisals' => $appraisals], 'Appraisals by employee retrieved.');
    }

    public function getAppraisalsByBranch($branchId)
    {
        // Check if branch belongs to user's company
        $branch = Branch::where('id', $branchId)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$branch) {
            return $this->error('Branch not found or access denied.', 404);
        }

        $appraisals = Appraisal::where('created_by', Auth::user()->creatorId())
            ->where('branch', $branchId)
            ->with(['employees', 'branches'])
            ->orderBy('appraisal_date', 'desc')
            ->get();

        return $this->success(['appraisals' => $appraisals], 'Appraisals by branch retrieved.');
    }

    public function getAppraisalsByDateRange(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date|date_format:Y-m-d',
            'end_date' => 'required|date|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $query = Appraisal::where('created_by', Auth::user()->creatorId())
            ->whereBetween('appraisal_date', [$request->start_date, $request->end_date]);

        if (Auth::user()->type == 'Employee') {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            $query->where('employee', $emp->id);
        }

        $appraisals = $query->with(['employees', 'branches'])
            ->orderBy('appraisal_date', 'desc')
            ->get();

        return $this->success(['appraisals' => $appraisals], 'Appraisals by date range retrieved.');
    }

    public function getAppraisalStats()
    {
        $query = Appraisal::where('created_by', Auth::user()->creatorId());

        if (Auth::user()->type == 'Employee') {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            $query->where('employee', $emp->id);
        }

        $totalAppraisals = $query->count();
        $currentYearAppraisals = $query->whereYear('appraisal_date', date('Y'))->count();
        $currentMonthAppraisals = $query->whereYear('appraisal_date', date('Y'))->whereMonth('appraisal_date', date('m'))->count();

        // Calculate average rating
        $appraisalsWithRatings = $query->get()->filter(function ($appraisal) {
            return !empty($appraisal->rating);
        });

        $totalRating = 0;
        $ratingCount = 0;
        foreach ($appraisalsWithRatings as $appraisal) {
            $ratings = json_decode($appraisal->rating, true);
            if (is_array($ratings)) {
                $totalRating += array_sum($ratings);
                $ratingCount += count($ratings);
            }
        }

        $averageRating = $ratingCount > 0 ? round($totalRating / $ratingCount, 2) : 0;

        // Group by employee
        $appraisalsByEmployee = $query->with('employees')
            ->get()
            ->groupBy('employees.name')
            ->map(function ($group) {
                return [
                    'employee_name' => $group->first()->employees->name,
                    'appraisal_count' => $group->count(),
                ];
            });

        // Group by branch
        $appraisalsByBranch = $query->with('branches')
            ->get()
            ->groupBy('branches.name')
            ->map(function ($group) {
                return [
                    'branch_name' => $group->first()->branches->name,
                    'appraisal_count' => $group->count(),
                ];
            });

        // Group by month
        $appraisalsByMonth = $query->get()
            ->groupBy(function ($appraisal) {
                return date('Y-m', strtotime($appraisal->appraisal_date));
            })
            ->map(function ($group) {
                return $group->count();
            });

        return $this->success([
            'total_appraisals' => $totalAppraisals,
            'current_year_appraisals' => $currentYearAppraisals,
            'current_month_appraisals' => $currentMonthAppraisals,
            'average_rating' => $averageRating,
            'appraisals_by_employee' => $appraisalsByEmployee,
            'appraisals_by_branch' => $appraisalsByBranch,
            'appraisals_by_month' => $appraisalsByMonth
        ], 'Appraisal statistics retrieved.');
    }

    public function getRecentAppraisals($limit = 10)
    {
        $query = Appraisal::where('created_by', Auth::user()->creatorId());

        if (Auth::user()->type == 'Employee') {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            $query->where('employee', $emp->id);
        }

        $appraisals = $query->with(['employees', 'branches'])
            ->orderBy('appraisal_date', 'desc')
            ->limit($limit)
            ->get();

        return $this->success(['appraisals' => $appraisals], 'Recent appraisals retrieved.');
    }
}
