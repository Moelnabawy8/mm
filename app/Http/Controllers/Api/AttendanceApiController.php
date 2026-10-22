<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AttendanceEmployee;
use App\Models\Employee;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Utility;
use App\Models\IpRestrict;
use App\Models\Project;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AttendanceApiController extends Controller
{
    use ApiResponser;

    public function getAttendance(Request $request)
    {
        $user = Auth::user();

        if ($user->type == 'employee') {
            $emp = Employee::where('user_id', $user->id)->first();
            if (!$emp) {
                return $this->success(['attendance' => []], 'No employee record found for this user.');
            }
            $attendance = AttendanceEmployee::where('employee_id', $emp->id)
                ->with(['employee'])
                ->orderBy('date', 'desc')
                ->get();
        } else {
            $attendance = AttendanceEmployee::where('created_by', $user->creatorId())
                ->with(['employee'])
                ->orderBy('date', 'desc')
                ->get();
        }

        return $this->success(['attendance' => $attendance], 'Attendance retrieved successfully.');
    }

    public function checkIn(Request $request)
    {
        $user = Auth::user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (!$employee) {
            return $this->error('Employee record not found. Please contact administrator to create your employee profile.', 404);
        }

        // Check IP restriction if enabled
        $settings = Utility::settings();
        if (isset($settings['ip_restrict']) && $settings['ip_restrict'] == 'on') {
            $userIp = $request->ip();
            $ip = IpRestrict::where('created_by', $user->creatorId())
                ->where('ip', $userIp)
                ->first();

            if (!$ip) {
                return $this->error('Your IP address is not allowed to check in.', 403);
            }
        }

        $date = $request->local_date ?? \Carbon\Carbon::now('Africa/Cairo')->format('Y-m-d');
        $time = $request->local_time ?? \Carbon\Carbon::now('Africa/Cairo')->format('H:i:s');

        // Check if already checked in today without checkout
        $existingAttendance = AttendanceEmployee::where('employee_id', $employee->id)
            ->where('date', $date)
            ->where('clock_out', '00:00:00')
            ->first();

        if ($existingAttendance) {
            return $this->error('You have an active check-in. Please check out first.', 400);
        }

        // Get company start time
        $startTime = Utility::getValByName('company_start_time') ?? '09:00:00';

        // Calculate late time
        $late = '00:00:00';
        $totalLateSeconds = strtotime($date . ' ' . $time) - strtotime($date . ' ' . $startTime);
        if ($totalLateSeconds > 0) {
            $hours = floor($totalLateSeconds / 3600);
            $mins = floor($totalLateSeconds / 60 % 60);
            $secs = floor($totalLateSeconds % 60);
            $late = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
        }

        // Create attendance record (supports multiple check-ins per day)
        $attendance = new AttendanceEmployee();
        $attendance->employee_id = $employee->id;
        $attendance->date = $date;
        $attendance->status = 'Present';
        $attendance->clock_in = $time;
        $attendance->clock_out = '00:00:00';
        $attendance->late = $late;
        $attendance->early_leaving = '00:00:00';
        $attendance->overtime = '00:00:00';
        $attendance->total_rest = '00:00:00';
        $attendance->created_by = $user->creatorId();
        $attendance->save();

        return $this->success(['attendance' => $attendance], 'Checked in successfully.');
    }

    public function checkOut(Request $request)
    {
        $user = Auth::user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (!$employee) {
            return $this->error('Employee record not found. Please contact administrator to create your employee profile.', 404);
        }

        $date = $request->local_date ?? \Carbon\Carbon::now('Africa/Cairo')->format('Y-m-d');
        $time = $request->local_time ?? \Carbon\Carbon::now('Africa/Cairo')->format('H:i:s');

        // Find today's latest active attendance (not checked out)
        $attendance = AttendanceEmployee::where('employee_id', $employee->id)
            ->where('date', $date)
            ->where('clock_out', '00:00:00')
            ->orderBy('id', 'desc')
            ->first();

        if (!$attendance) {
            return $this->error('You must check-in first before checking out.', 400);
        }

        // Get company end time
        $endTime = Utility::getValByName('company_end_time') ?? '18:00:00';

        // Calculate early leaving and overtime
        $earlyLeaving = '00:00:00';
        $overtime = '00:00:00';

        $totalEarlyLeavingSeconds = strtotime($date . ' ' . $endTime) - strtotime($date . ' ' . $time);
        if ($totalEarlyLeavingSeconds > 0) {
            $hours = floor($totalEarlyLeavingSeconds / 3600);
            $mins = floor($totalEarlyLeavingSeconds / 60 % 60);
            $secs = floor($totalEarlyLeavingSeconds % 60);
            $earlyLeaving = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
        } else {
            // Calculate overtime
            $totalOvertimeSeconds = strtotime($date . ' ' . $time) - strtotime($date . ' ' . $endTime);
            if ($totalOvertimeSeconds > 0) {
                $hours = floor($totalOvertimeSeconds / 3600);
                $mins = floor($totalOvertimeSeconds / 60 % 60);
                $secs = floor($totalOvertimeSeconds % 60);
                $overtime = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
            }
        }

        // Update attendance
        $attendance->clock_out = $time;
        $attendance->early_leaving = $earlyLeaving;
        $attendance->overtime = $overtime;
        $attendance->save();

        return $this->success(['attendance' => $attendance], 'Checked out successfully.');
    }

    public function getTodayAttendance()
    {
        $user = Auth::user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (!$employee) {
            return $this->success([
                'attendance' => null,
                'status' => 'not_checked_in',
                'message' => 'You have not checked in today.'
            ], 'No attendance record for today.');
        }

        $date = date('Y-m-d');
        $attendance = AttendanceEmployee::where('employee_id', $employee->id)
            ->where('date', $date)
            ->first();

        if (!$attendance) {
            return $this->success([
                'attendance' => null,
                'status' => 'not_checked_in',
                'message' => 'You have not checked in today.'
            ], 'No attendance record for today.');
        }

        $status = 'checked_in';
        if ($attendance->clock_out != '00:00:00') {
            $status = 'checked_out';
        }

        return $this->success([
            'attendance' => $attendance,
            'status' => $status
        ], 'Today attendance retrieved.');
    }

    public function markAttendance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'clock_in' => 'nullable|date_format:H:i',
            'clock_out' => 'nullable|date_format:H:i',
            'date' => 'nullable|date|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Check IP restriction if enabled
        $settings = Utility::settings();
        if (isset($settings['ip_restrict']) && $settings['ip_restrict'] == 'on') {
            $userIp = $request->ip();
            $ip = IpRestrict::where('created_by', Auth::user()->creatorId())
                ->whereIn('ip', [$userIp])
                ->first();

            if (empty($ip)) {
                return $this->error('This IP is not allowed to clock in & clock out.', 403);
            }
        }

        $employeeId = !empty(Auth::user()->employee) ? Auth::user()->employee->id : 0;
        $date = $request->date ?: date('Y-m-d');
        $time = date('H:i:s');

        // Get company time settings
        $startTime = Utility::getValByName('company_start_time');
        $endTime = Utility::getValByName('company_end_time');

        // Check for existing attendance for the day
        $existingAttendance = AttendanceEmployee::where('employee_id', $employeeId)
            ->where('date', $date)
            ->first();

        if ($existingAttendance && $existingAttendance->clock_out != '00:00:00') {
            return $this->error('Employee already clocked out for today.', 400);
        }

        // Calculate times
        $clockInTime = $request->clock_in ?: $time;
        $clockOutTime = $request->clock_out ?: '00:00:00';

        $late = '00:00:00';
        $earlyLeaving = '00:00:00';
        $overtime = '00:00:00';

        if ($clockInTime && $clockInTime != '00:00:00') {
            $totalLateSeconds = strtotime($date . ' ' . $clockInTime) - strtotime($date . ' ' . $startTime);
            $totalLateSeconds = max($totalLateSeconds, 0);

            $hours = floor($totalLateSeconds / 3600);
            $mins = floor($totalLateSeconds / 60 % 60);
            $secs = floor($totalLateSeconds % 60);
            $late = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
        }

        if ($clockOutTime && $clockOutTime != '00:00:00') {
            $totalEarlyLeavingSeconds = strtotime($date . ' ' . $endTime) - strtotime($date . ' ' . $clockOutTime);
            $totalEarlyLeavingSeconds = max($totalEarlyLeavingSeconds, 0);

            $hours = floor($totalEarlyLeavingSeconds / 3600);
            $mins = floor($totalEarlyLeavingSeconds / 60 % 60);
            $secs = floor($totalEarlyLeavingSeconds % 60);
            $earlyLeaving = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);

            if (strtotime($date . ' ' . $clockOutTime) > strtotime($date . ' ' . $endTime)) {
                $totalOvertimeSeconds = strtotime($date . ' ' . $clockOutTime) - strtotime($date . ' ' . $endTime);
                $hours = floor($totalOvertimeSeconds / 3600);
                $mins = floor($totalOvertimeSeconds / 60 % 60);
                $secs = floor($totalOvertimeSeconds % 60);
                $overtime = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
            }
        }

        // Create or update attendance record
        if ($existingAttendance) {
            $existingAttendance->update([
                'clock_in' => $clockInTime,
                'clock_out' => $clockOutTime,
                'late' => $late,
                'early_leaving' => $earlyLeaving,
                'overtime' => $overtime,
            ]);

            return $this->success(['attendance' => $existingAttendance], 'Attendance updated successfully.');
        } else {
            $attendance = new AttendanceEmployee();
            $attendance->employee_id = $employeeId;
            $attendance->date = $date;
            $attendance->status = $clockInTime ? 'Present' : 'Leave';
            $attendance->clock_in = $clockInTime;
            $attendance->clock_out = $clockOutTime;
            $attendance->late = $late;
            $attendance->early_leaving = $earlyLeaving;
            $attendance->overtime = $overtime;
            $attendance->total_rest = '00:00:00';
            $attendance->created_by = Auth::user()->creatorId();
            $attendance->save();

            return $this->success(['attendance' => $attendance], 'Attendance marked successfully.');
        }
    }

    public function bulkAttendance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date|date_format:Y-m-d',
            'employees' => 'required|array',
            'employees.*.employee_id' => 'required|exists:employees,id',
            'employees.*.clock_in' => 'nullable|date_format:H:i',
            'employees.*.clock_out' => 'nullable|date_format:H:i',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Get company time settings
        $startTime = Utility::getValByName('company_start_time');
        $endTime = Utility::getValByName('company_end_time');
        $date = $request->date;

        $results = [];
        $errors = [];

        foreach ($request->employees as $employeeData) {
            try {
                $employee = Employee::where('id', $employeeData['employee_id'])
                    ->where('created_by', Auth::user()->creatorId())
                    ->first();

                if (!$employee) {
                    $errors[] = "Employee ID {$employeeData['employee_id']} not found.";
                    continue;
                }

                $clockIn = $employeeData['clock_in'] ?? '00:00:00';
                $clockOut = $employeeData['clock_out'] ?? '00:00:00';
                $status = $clockIn ? 'Present' : 'Leave';

                $late = '00:00:00';
                $earlyLeaving = '00:00:00';
                $overtime = '00:00:00';

                if ($clockIn && $clockIn != '00:00:00') {
                    $totalLateSeconds = strtotime($date . ' ' . $clockIn) - strtotime($date . ' ' . $startTime);
                    $totalLateSeconds = max($totalLateSeconds, 0);

                    $hours = floor($totalLateSeconds / 3600);
                    $mins = floor($totalLateSeconds / 60 % 60);
                    $secs = floor($totalLateSeconds % 60);
                    $late = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
                }

                if ($clockOut && $clockOut != '00:00:00') {
                    $totalEarlyLeavingSeconds = strtotime($date . ' ' . $endTime) - strtotime($date . ' ' . $clockOut);
                    $totalEarlyLeavingSeconds = max($totalEarlyLeavingSeconds, 0);

                    $hours = floor($totalEarlyLeavingSeconds / 3600);
                    $mins = floor($totalEarlyLeavingSeconds / 60 % 60);
                    $secs = floor($totalEarlyLeavingSeconds % 60);
                    $earlyLeaving = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);

                    if (strtotime($date . ' ' . $clockOut) > strtotime($date . ' ' . $endTime)) {
                        $totalOvertimeSeconds = strtotime($date . ' ' . $clockOut) - strtotime($date . ' ' . $endTime);
                        $hours = floor($totalOvertimeSeconds / 3600);
                        $mins = floor($totalOvertimeSeconds / 60 % 60);
                        $secs = floor($totalOvertimeSeconds % 60);
                        $overtime = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
                    }
                }

                // Check for existing attendance
                $existingAttendance = AttendanceEmployee::where('employee_id', $employee->id)
                    ->where('date', $date)
                    ->first();

                if ($existingAttendance) {
                    $existingAttendance->update([
                        'status' => $status,
                        'clock_in' => $clockIn,
                        'clock_out' => $clockOut,
                        'late' => $late,
                        'early_leaving' => $earlyLeaving,
                        'overtime' => $overtime,
                    ]);
                    $attendance = $existingAttendance;
                } else {
                    $attendance = new AttendanceEmployee();
                    $attendance->employee_id = $employee->id;
                    $attendance->date = $date;
                    $attendance->status = $status;
                    $attendance->clock_in = $clockIn;
                    $attendance->clock_out = $clockOut;
                    $attendance->late = $late;
                    $attendance->early_leaving = $earlyLeaving;
                    $attendance->overtime = $overtime;
                    $attendance->total_rest = '00:00:00';
                    $attendance->created_by = Auth::user()->creatorId();
                    $attendance->save();
                }

                $results[] = $attendance;
            } catch (\Exception $e) {
                $errors[] = "Error processing employee {$employeeData['employee_id']}: " . $e->getMessage();
            }
        }

        return $this->success([
            'results' => $results,
            'errors' => $errors
        ], 'Bulk attendance processed.');
    }

    public function exportAttendance()
    {
        if (!Auth::user()->can('manage attendance')) {
            return $this->error('Permission denied.', 403);
        }

        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\AttendanceExport(), 'attendance_' . date('Y-m-d_H-i-s') . '.xlsx');
    }

    /**
     * Upload a .csv/.xlsx whose first row is a header with: email (or employee_email),
     * date, clock_in, clock_out. One row per employee per day; an existing row for
     * that employee and date is overwritten. The web importer does the same thing
     * through a session-backed column-mapping screen, which an API client cannot use.
     */
    public function importAttendance(Request $request)
    {
        if (!Auth::user()->can('create attendance')) {
            return $this->error('Permission denied.', 403);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $sheets = \Maatwebsite\Excel\Facades\Excel::toArray(new class implements \Maatwebsite\Excel\Concerns\ToArray {
            public function array(array $rows) {}
        }, $request->file('file'));
        $rows = $sheets[0] ?? [];
        if (count($rows) < 2) {
            return $this->error('The file has no data rows.', 422);
        }

        $header = array_map(fn ($h) => str_replace([' ', '-'], '_', strtolower(trim((string) $h))), array_shift($rows));
        $col = [
            'email' => array_search('email', $header) !== false ? array_search('email', $header) : array_search('employee_email', $header),
            'date' => array_search('date', $header),
            'clock_in' => array_search('clock_in', $header),
            'clock_out' => array_search('clock_out', $header),
        ];
        foreach (['email', 'date', 'clock_in'] as $required) {
            if ($col[$required] === false) {
                return $this->error("Missing column '{$required}'. Expected header: email, date, clock_in, clock_out", 422);
            }
        }
        if (count($rows) > 5000) {
            return $this->error('Too many rows (max 5000 per file).', 422);
        }

        $creatorId = Auth::user()->creatorId();
        $employees = Employee::where('created_by', $creatorId)->get(['id', 'email'])
            ->keyBy(fn ($e) => strtolower(trim($e->email)));
        $startTime = Utility::getValByName('company_start_time') ?: '09:00:00';
        $endTime = Utility::getValByName('company_end_time') ?: '18:00:00';

        $imported = 0;
        $failed = [];
        foreach ($rows as $i => $row) {
            $line = $i + 2; // 1-based, after the header
            $email = strtolower(trim((string) ($row[$col['email']] ?? '')));
            if ($email === '' && empty(array_filter($row))) {
                continue;
            }
            $employee = $employees->get($email);
            $date = $this->importDate($row[$col['date']] ?? null);
            $clockIn = $this->importTime($row[$col['clock_in']] ?? null);
            $clockOut = $col['clock_out'] !== false ? $this->importTime($row[$col['clock_out']] ?? null) : '00:00:00';

            if (!$employee) {
                $failed[] = ['row' => $line, 'email' => $email, 'reason' => 'No employee with this email in your company.'];
                continue;
            }
            if (!$date || $clockIn === null || $clockOut === null) {
                $failed[] = ['row' => $line, 'email' => $email, 'reason' => 'Invalid date or time.'];
                continue;
            }

            $values = [
                'status' => $clockIn !== '00:00:00' ? 'Present' : 'Leave',
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,
                'late' => $clockIn !== '00:00:00' ? $this->secondsToTime(strtotime("$date $clockIn") - strtotime("$date $startTime")) : '00:00:00',
                'early_leaving' => $clockOut !== '00:00:00' ? $this->secondsToTime(strtotime("$date $endTime") - strtotime("$date $clockOut")) : '00:00:00',
                'overtime' => $clockOut !== '00:00:00' ? $this->secondsToTime(strtotime("$date $clockOut") - strtotime("$date $endTime")) : '00:00:00',
            ];

            $attendance = AttendanceEmployee::where('employee_id', $employee->id)->where('date', $date)->first();
            if ($attendance) {
                $attendance->update($values);
            } else {
                $attendance = new AttendanceEmployee();
                $attendance->forceFill($values + [
                    'employee_id' => $employee->id,
                    'date' => $date,
                    'total_rest' => '00:00:00',
                    'created_by' => $creatorId,
                ])->save();
            }
            $imported++;
        }

        return $this->success(['imported' => $imported, 'failed' => $failed], "Imported {$imported} row(s), " . count($failed) . ' failed.');
    }

    private function importDate($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) { // Excel serial date
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
        }
        $ts = strtotime((string) $value);

        return $ts ? date('Y-m-d', $ts) : null;
    }

    // Empty cell = no clock (00:00:00); unparseable = null.
    private function importTime($value)
    {
        if ($value === null || trim((string) $value) === '') {
            return '00:00:00';
        }
        if (is_numeric($value) && $value < 1) { // Excel time fraction of a day
            return gmdate('H:i:s', (int) round($value * 86400));
        }
        $ts = strtotime('2000-01-01 ' . trim((string) $value));

        return $ts ? date('H:i:s', $ts) : null;
    }

    private function secondsToTime($seconds)
    {
        $seconds = max((int) $seconds, 0);

        return sprintf('%02d:%02d:%02d', floor($seconds / 3600), floor($seconds / 60 % 60), $seconds % 60);
    }

    public function getAttendanceDetail($id)
    {
        $attendance = AttendanceEmployee::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with(['employee'])
            ->first();

        if (!$attendance) {
            return $this->error('Attendance not found', 404);
        }

        // Check if employee has access to this attendance
        if (Auth::user()->type == 'Employee') {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            if ($attendance->employee_id != $emp->id) {
                return $this->error('Permission denied.', 403);
            }
        }

        return $this->success(['attendance' => $attendance], 'Attendance detail retrieved.');
    }

    public function updateAttendance(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'clock_in' => 'nullable|date_format:H:i',
            'clock_out' => 'nullable|date_format:H:i',
            'date' => 'nullable|date|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $attendance = AttendanceEmployee::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$attendance) {
            return $this->error('Attendance not found', 404);
        }

        // Check if employee has access to update this attendance
        if (Auth::user()->type == 'Employee') {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            if ($attendance->employee_id != $emp->id) {
                return $this->error('Permission denied.', 403);
            }
        }

        // Only allow updating current day's attendance for employees
        if (Auth::user()->type == 'Employee' && $attendance->date != date('Y-m-d')) {
            return $this->error('You can only update current day attendance.', 400);
        }

        // Get company time settings
        $startTime = Utility::getValByName('company_start_time');
        $endTime = Utility::getValByName('company_end_time');
        $date = $request->date ?: $attendance->date;

        $clockIn = $request->clock_in ?: $attendance->clock_in;
        $clockOut = $request->clock_out ?: $attendance->clock_out;

        $late = '00:00:00';
        $earlyLeaving = '00:00:00';
        $overtime = '00:00:00';

        if ($clockIn && $clockIn != '00:00:00') {
            $totalLateSeconds = strtotime($date . ' ' . $clockIn) - strtotime($date . ' ' . $startTime);
            $totalLateSeconds = max($totalLateSeconds, 0);

            $hours = floor($totalLateSeconds / 3600);
            $mins = $totalLateSeconds / 60 % 60;
            $secs = floor($totalLateSeconds % 60);
            $late = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
        }

        if ($clockOut && $clockOut != '00:00:00') {
            $totalEarlyLeavingSeconds = strtotime($date . ' ' . $endTime) - strtotime($date . ' ' . $clockOut);
            $totalEarlyLeavingSeconds = max($totalEarlyLeavingSeconds, 0);

            $hours = floor($totalEarlyLeavingSeconds / 3600);
            $mins = floor($totalEarlyLeavingSeconds / 60 % 60);
            $secs = floor($totalEarlyLeavingSeconds % 60);
            $earlyLeaving = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);

            if (strtotime($date . ' ' . $clockOut) > strtotime($date . ' ' . $endTime)) {
                $totalOvertimeSeconds = strtotime($date . ' ' . $clockOut) - strtotime($date . ' ' . $endTime);
                $hours = floor($totalOvertimeSeconds / 3600);
                $mins = floor($totalOvertimeSeconds / 60 % 60);
                $secs = floor($totalOvertimeSeconds % 60);
                $overtime = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
            }
        }

        $attendance->clock_in = $clockIn;
        $attendance->clock_out = $clockOut;
        $attendance->late = $late;
        $attendance->early_leaving = $earlyLeaving;
        $attendance->overtime = $overtime;
        $attendance->save();

        return $this->success(['attendance' => $attendance], 'Attendance updated successfully.');
    }

    public function deleteAttendance($id)
    {
        $attendance = AttendanceEmployee::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$attendance) {
            return $this->error('Attendance not found', 404);
        }

        // Check if employee has access to delete this attendance
        if (Auth::user()->type == 'Employee') {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            if ($attendance->employee_id != $emp->id) {
                return $this->error('Permission denied.', 403);
            }
        }

        $attendance->delete();

        return $this->success([], 'Attendance deleted successfully.');
    }

    public function getEmployeeAttendanceList($id)
    {
        // Check if employee belongs to user's company
        $employee = Employee::where('user_id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$employee) {
            return $this->error('Employee not found or access denied.', 404);
        }

        $attendance = AttendanceEmployee::where('employee_id', $employee->id)
            ->orderBy('date', 'desc')
            ->get();

        $attendance = $attendance->map(function ($record) use ($id) {
            return [
                'id' => $record->id,
                'employee_id' => (int)$id,
                'date' => $record->date,
                'status' => $record->status,
                'clock_in' => $record->clock_in,
                'clock_out' => $record->clock_out,
                'late' => $record->late,
                'early_leaving' => $record->early_leaving,
                'overtime' => $record->overtime,
                'total_rest' => $record->total_rest,
                'employee_name' => $record->employee_name,
                'department_name' => $record->department_name,
            ];
        });

        return $this->success(['attendance' => $attendance], 'Employee attendance retrieved.');
    }

    public function getAttendanceByDateRange(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date|date_format:Y-m-d',
            'end_date' => 'required|date|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $query = AttendanceEmployee::whereBetween('date', [$request->start_date, $request->end_date])
            ->with(['employee']);

        if (Auth::user()->type == 'Employee') {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            $query->where('employee_id', $emp->id);
        } else {
            $query->where('created_by', Auth::user()->creatorId());
        }

        $attendance = $query->orderBy('date', 'desc')->get();

        $attendance = $attendance->map(function ($record) {
            $userId = $record->employee ? $record->employee->user_id : null;
            return [
                'id' => $record->id,
                'employee_id' => $userId,
                'date' => $record->date,
                'status' => $record->status,
                'clock_in' => $record->clock_in,
                'clock_out' => $record->clock_out,
                'late' => $record->late,
                'early_leaving' => $record->early_leaving,
                'overtime' => $record->overtime,
                'total_rest' => $record->total_rest,
                'employee_name' => $record->employee_name,
                'department_name' => $record->department_name,
            ];
        });

        return $this->success(['attendance' => $attendance], 'Attendance by date range retrieved.');
    }

    public function getAttendanceStats(Request $request)
    {
        $query = AttendanceEmployee::where('created_by', Auth::user()->creatorId());

        if (Auth::user()->type == 'Employee') {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            $query->where('employee_id', $emp->id);
        }

        $totalDays = $query->count();
        $presentDays = $query->where('status', 'Present')->count();
        $leaveDays = $query->where('status', 'Leave')->count();

        // Calculate total overtime
        $totalOvertime = $query->sum('overtime');
        $totalLate = $query->sum('late');
        $totalEarlyLeaving = $query->sum('early_leaving');

        // Group by employee
        $attendanceByEmployee = $query->with('employee')
            ->get()
            ->groupBy('employee.name')
            ->map(function ($group) {
                return [
                    'employee_name' => $group->first()->employee->name,
                    'total_days' => $group->count(),
                    'present_days' => $group->where('status', 'Present')->count(),
                    'leave_days' => $group->where('status', 'Leave')->count(),
                ];
            });

        // Group by date
        $attendanceByDate = $query->get()
            ->groupBy('date')
            ->map(function ($group) {
                return [
                    'date' => $group->first()->date,
                    'total_attendance' => $group->count(),
                    'present_count' => $group->where('status', 'Present')->count(),
                    'leave_count' => $group->where('status', 'Leave')->count(),
                ];
            });

        return $this->success([
            'total_days' => $totalDays,
            'present_days' => $presentDays,
            'leave_days' => $leaveDays,
            'total_overtime' => $totalOvertime,
            'total_late' => $totalLate,
            'total_early_leaving' => $totalEarlyLeaving,
            'attendance_by_employee' => $attendanceByEmployee,
            'attendance_by_date' => $attendanceByDate
        ], 'Attendance statistics retrieved.');
    }

    public function getMyAttendance()
    {
        if (Auth::user()->type != 'Employee') {
            return $this->error('This endpoint is for employees only.', 403);
        }

        $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
        $attendance = AttendanceEmployee::where('employee_id', $emp->id)
            ->orderBy('date', 'desc')
            ->get();

        return $this->success(['attendance' => $attendance], 'My attendance retrieved.');
    }

    public function getCompanyTimeSettings()
    {
        $startTime = Utility::getValByName('company_start_time');
        $endTime = Utility::getValByName('company_end_time');

        return $this->success([
            'start_time' => $startTime,
            'end_time' => $endTime
        ], 'Company time settings retrieved.');
    }

    public function getEmployees()
    {
        $employees = Employee::where('created_by', Auth::user()->creatorId())
            ->get(['id', 'name', 'email']);

        return $this->success(['employees' => $employees], 'Employees retrieved.');
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

    public function getDepartmentProjects(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'department_id' => 'required|exists:departments,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $department = Department::where('id', $request->department_id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$department) {
            return $this->error('Department not found or access denied.', 404);
        }

        // Get employees in this department
        $employeeIds = Employee::where('department_id', $department->id)
            ->where('created_by', Auth::user()->creatorId())
            ->pluck('id')
            ->toArray();

        if (empty($employeeIds)) {
            return $this->success([
                'department' => $department,
                'projects' => []
            ], 'No employees found in this department.');
        }

        // Get user IDs from employees
        $userIds = Employee::whereIn('id', $employeeIds)
            ->pluck('user_id')
            ->toArray();

        // Get projects assigned to these users
        $projects = DB::table('projects')
            ->select('projects.*')
            ->join('project_users', 'projects.id', '=', 'project_users.project_id')
            ->whereIn('project_users.user_id', $userIds)
            ->where('projects.created_by', Auth::user()->creatorId())
            ->distinct()
            ->get();

        // Format projects with additional info
        $formattedProjects = $projects->map(function ($project) {
            return [
                'id' => $project->id,
                'project_name' => $project->project_name,
                'start_date' => $project->start_date,
                'end_date' => $project->end_date,
                'budget' => $project->budget,
                'status' => $project->status,
                'description' => $project->description,
                'created_at' => $project->created_at,
            ];
        });

        return $this->success([
            'department' => [
                'id' => $department->id,
                'name' => $department->name,
            ],
            'projects' => $formattedProjects,
            'total_projects' => $formattedProjects->count()
        ], 'Department projects retrieved successfully.');
    }

    public function getMultipleAttendanceRecords(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'nullable|date|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $user = Auth::user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (!$employee) {
            return $this->error('Employee record not found.', 404);
        }

        $date = $request->date ?? date('Y-m-d');

        // Get all attendance records for the specified date
        $attendances = AttendanceEmployee::where('employee_id', $employee->id)
            ->where('date', $date)
            ->orderBy('clock_in', 'asc')
            ->get();

        // Calculate total hours worked
        $totalSeconds = 0;
        foreach ($attendances as $attendance) {
            if ($attendance->clock_out != '00:00:00') {
                $clockIn = strtotime($date . ' ' . $attendance->clock_in);
                $clockOut = strtotime($date . ' ' . $attendance->clock_out);
                $totalSeconds += ($clockOut - $clockIn);
            }
        }

        $totalHours = floor($totalSeconds / 3600);
        $totalMins = floor(($totalSeconds % 3600) / 60);
        $totalWorked = sprintf('%02d:%02d', $totalHours, $totalMins);

        return $this->success([
            'date' => $date,
            'attendances' => $attendances,
            'total_records' => $attendances->count(),
            'total_hours_worked' => $totalWorked,
            'active_checkin' => $attendances->where('clock_out', '00:00:00')->first()
        ], 'Multiple attendance records retrieved successfully.');
    }
}
