<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;

class OrgChartController extends Controller
{
    public function index()
    {
        $creatorId = \Auth::user()->creatorId();

        $departments = Department::where('created_by', $creatorId)->get();

        $employees = Employee::with(['user'])
            ->where('created_by', $creatorId)
            ->get();

        // Group employees by department
        $byDepartment = $employees->groupBy('department_id');

        // Build org data: [{department, employees:[]}]
        $orgData = $departments->map(function ($dept) use ($byDepartment) {
            return [
                'id'        => $dept->id,
                'name'      => $dept->name,
                'employees' => ($byDepartment->get($dept->id) ?? collect())->map(function ($emp) {
                    return [
                        'id'          => $emp->id,
                        'name'        => $emp->name,
                        'designation' => optional($emp->designation)->name ?? '-',
                        'avatar'      => $emp->avatar ?? null,
                    ];
                })->values()->all(),
            ];
        });

        $totalEmployees  = $employees->count();
        $totalDepartments = $departments->count();

        return view('orgchart.index', compact('orgData', 'totalEmployees', 'totalDepartments'));
    }
}
