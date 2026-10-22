<?php

namespace App\Exports;

use App\Models\AttendanceEmployee;
use App\Models\Employee;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AttendanceExport implements FromCollection, WithHeadings
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        // Get all employees for the current user's organization
        $employees = Employee::where('created_by', \Auth::user()->creatorId())->pluck('id');

        // Get attendance records for these employees
        $data = AttendanceEmployee::whereIn('employee_id', $employees)->get();

        foreach ($data as $k => $attendance) {
            // Remove unnecessary fields
            unset($attendance->id, $attendance->created_by, $attendance->created_at, $attendance->updated_at);

            // Transform employee_id to employee name
            $data[$k]["employee_id"] = !empty($attendance->employee) ? $attendance->employee->name : '-';

            // Add employee details
            $data[$k]["employee_email"] = !empty($attendance->employee) ? $attendance->employee->email : '-';
            $data[$k]["branch"] = !empty($attendance->employee) && !empty($attendance->employee->branch)
                ? $attendance->employee->branch->name : '-';
            $data[$k]["department"] = !empty($attendance->employee) && !empty($attendance->employee->department)
                ? $attendance->employee->department->name : '-';
            $data[$k]["designation"] = !empty($attendance->employee) && !empty($attendance->employee->designation)
                ? $attendance->employee->designation->name : '-';

            // Format date
            $data[$k]["date"] = \Auth::user()->dateFormat($attendance->date);

            // Format time fields (remove seconds if needed)
            $data[$k]["clock_in"] = $attendance->clock_in != '00:00:00'
                ? \Auth::user()->timeFormat($attendance->clock_in) : '00:00';
            $data[$k]["clock_out"] = $attendance->clock_out != '00:00:00'
                ? \Auth::user()->timeFormat($attendance->clock_out) : '00:00';
        }

        return $data;
    }

    public function headings(): array
    {
        return [
            "Employee Name",
            "Date",
            "Status",
            "Clock In",
            "Clock Out",
            "Late",
            "Early Leaving",
            "Overtime",
            "Total Rest",
            "Employee Email",
            "Branch",
            "Department",
            "Designation",
        ];
    }
}
