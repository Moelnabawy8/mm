<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Announcement;
use App\Models\AnnouncementEmployee;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Utility;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AnnouncementApiController extends Controller
{
    use ApiResponser;

    public function getAnnouncements()
    {
        $user = Auth::user();

        if ($user->type == 'Employee') {
            $currentEmployee = Employee::where('user_id', '=', $user->id)->first();
            $announcements = Announcement::orderBy('id', 'desc')
                ->leftJoin('announcement_employees', 'announcements.id', '=', 'announcement_employees.announcement_id')
                ->where('announcement_employees.employee_id', '=', $currentEmployee->id)
                ->orWhere(function ($q) {
                    $q->where('announcements.department_id', '["0"]')->where('announcements.employee_id', '["0"]');
                })
                ->get();
        } else {
            $announcements = Announcement::where('created_by', '=', $user->creatorId())
                ->orderBy('id', 'desc')
                ->get();
        }

        return $this->success(['announcements' => $announcements], 'Announcements retrieved.');
    }

    public function createAnnouncement(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'start_date' => 'required|date|date_format:Y-m-d',
            'end_date' => 'required|date|date_format:Y-m-d|after_or_equal:start_date',
            'branch_id' => 'required|integer|min:0',
            'department_id' => 'required|array',
            'department_id.*' => 'exists:departments,id',
            'employee_id' => 'required|array',
            'employee_id.*' => 'exists:employees,id',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Check if branch belongs to user's company (if not 0 for all branches)
        if ($request->branch_id != 0) {
            $branch = Branch::where('id', $request->branch_id)
                ->where('created_by', Auth::user()->creatorId())
                ->first();

            if (!$branch) {
                return $this->error('Branch not found or access denied.', 404);
            }
        }

        // Check if departments belong to user's company
        if (!in_array('0', $request->department_id)) {
            $departments = Department::whereIn('id', $request->department_id)
                ->where('created_by', Auth::user()->creatorId())
                ->count();

            if ($departments != count($request->department_id)) {
                return $this->error('One or more departments not found or access denied.', 404);
            }
        }

        // Check if employees belong to user's company
        if (!in_array('0', $request->employee_id)) {
            $employees = Employee::whereIn('id', $request->employee_id)
                ->where('created_by', Auth::user()->creatorId())
                ->count();

            if ($employees != count($request->employee_id)) {
                return $this->error('One or more employees not found or access denied.', 404);
            }
        }

        $announcement = new Announcement();
        $announcement->title = $request->title;
        $announcement->start_date = $request->start_date;
        $announcement->end_date = $request->end_date;
        $announcement->branch_id = $request->branch_id;
        $announcement->department_id = json_encode($request->department_id);
        $announcement->employee_id = json_encode($request->employee_id);
        $announcement->description = $request->description;
        $announcement->created_by = Auth::user()->creatorId();
        $announcement->save();

        // Create announcement employee records for targeted employees
        $departmentEmployee = $this->getTargetEmployees($request->branch_id, $request->department_id, $request->employee_id);

        foreach ($departmentEmployee as $employeeId) {
            $announcementEmployee = new AnnouncementEmployee();
            $announcementEmployee->announcement_id = $announcement->id;
            $announcementEmployee->employee_id = $employeeId;
            $announcementEmployee->created_by = Auth::user()->creatorId();
            $announcementEmployee->save();
        }

        // Send notifications
        $setting = Utility::settings(Auth::user()->creatorId());

        $branchName = 'All';
        if ($request->branch_id != 0) {
            $branch = Branch::find($request->branch_id);
            $branchName = $branch ? $branch->name : 'All';
        }

        $announceNotificationArr = [
            'announcement_title' => $request->title,
            'branch_name' => $branchName,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ];

        // Slack Notification
        if (isset($setting['announcement_notification']) && $setting['announcement_notification'] == 1) {
            Utility::send_slack_msg('new_announcement', $announceNotificationArr);
        }

        // Telegram Notification
        if (isset($setting['telegram_announcement_notification']) && $setting['telegram_announcement_notification'] == 1) {
            Utility::send_telegram_msg('new_announcement', $announceNotificationArr);
        }

        // Webhook
        $module = 'New Announcement';
        $webhook = Utility::webhookSetting($module);
        if ($webhook) {
            $parameter = json_encode($announcement);
            $status = Utility::WebhookCall($webhook['url'], $parameter, $webhook['method']);
        }

        return $this->success(['announcement' => $announcement], 'Announcement created successfully.');
    }

    public function getAnnouncement($id)
    {
        $announcement = Announcement::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$announcement) {
            return $this->error('Announcement not found.', 404);
        }

        // Decode JSON fields for API response
        $announcement->department_id_decoded = json_decode($announcement->department_id, true);
        $announcement->employee_id_decoded = json_decode($announcement->employee_id, true);

        return $this->success(['announcement' => $announcement], 'Announcement detail.');
    }

    public function updateAnnouncement(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'start_date' => 'required|date|date_format:Y-m-d',
            'end_date' => 'required|date|date_format:Y-m-d|after_or_equal:start_date',
            'branch_id' => 'required|integer|min:0',
            'department_id' => 'required|array',
            'department_id.*' => 'exists:departments,id',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $announcement = Announcement::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$announcement) {
            return $this->error('Announcement not found.', 404);
        }

        // Check if branch belongs to user's company (if not 0 for all branches)
        if ($request->branch_id != 0) {
            $branch = Branch::where('id', $request->branch_id)
                ->where('created_by', Auth::user()->creatorId())
                ->first();

            if (!$branch) {
                return $this->error('Branch not found or access denied.', 404);
            }
        }

        // Check if departments belong to user's company
        if (!in_array('0', $request->department_id)) {
            $departments = Department::whereIn('id', $request->department_id)
                ->where('created_by', Auth::user()->creatorId())
                ->count();

            if ($departments != count($request->department_id)) {
                return $this->error('One or more departments not found or access denied.', 404);
            }
        }

        $announcement->title = $request->title;
        $announcement->start_date = $request->start_date;
        $announcement->end_date = $request->end_date;
        $announcement->branch_id = $request->branch_id;
        $announcement->department_id = json_encode($request->department_id);
        $announcement->description = $request->description;
        $announcement->save();

        return $this->success(['announcement' => $announcement], 'Announcement updated successfully.');
    }

    public function deleteAnnouncement($id)
    {
        $announcement = Announcement::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$announcement) {
            return $this->error('Announcement not found.', 404);
        }

        // Delete associated announcement employee records
        AnnouncementEmployee::where('announcement_id', $id)->delete();
        $announcement->delete();

        return $this->success([], 'Announcement deleted successfully.');
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

        if ($branchId && $branchId != '0') {
            // Check if branch belongs to user's company
            $branch = Branch::where('id', $branchId)
                ->where('created_by', Auth::user()->creatorId())
                ->first();

            if (!$branch) {
                return $this->error('Branch not found or access denied.', 404);
            }

            $query->where('branch_id', $branchId);
        }

        $departments = $query->get(['id', 'name']);

        return $this->success(['departments' => $departments], 'Departments retrieved.');
    }

    public function getEmployees($departmentId = null)
    {
        $query = Employee::where('created_by', Auth::user()->creatorId());

        if ($departmentId && $departmentId != '0') {
            // Check if department belongs to user's company
            $department = Department::where('id', $departmentId)
                ->where('created_by', Auth::user()->creatorId())
                ->first();

            if (!$department) {
                return $this->error('Department not found or access denied.', 404);
            }

            $query->where('department_id', $departmentId);
        }

        $employees = $query->get(['id', 'name']);

        return $this->success(['employees' => $employees], 'Employees retrieved.');
    }

    public function getActiveAnnouncements()
    {
        $currentDate = date('Y-m-d');

        $query = Announcement::where('created_by', Auth::user()->creatorId())
            ->where('start_date', '<=', $currentDate)
            ->where('end_date', '>=', $currentDate);

        if (Auth::user()->type == 'Employee') {
            $currentEmployee = Employee::where('user_id', '=', Auth::user()->id)->first();
            $query->leftJoin('announcement_employees', 'announcements.id', '=', 'announcement_employees.announcement_id')
                ->where('announcement_employees.employee_id', '=', $currentEmployee->id)
                ->orWhere(function ($q) {
                    $q->where('announcements.department_id', '["0"]')->where('announcements.employee_id', '["0"]');
                });
        }

        $announcements = $query->orderBy('start_date', 'desc')->get();

        return $this->success(['announcements' => $announcements], 'Active announcements retrieved.');
    }

    public function getAnnouncementsByDateRange(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date|date_format:Y-m-d',
            'end_date' => 'required|date|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $query = Announcement::where('created_by', Auth::user()->creatorId())
            ->whereBetween('start_date', [$request->start_date, $request->end_date]);

        if (Auth::user()->type == 'Employee') {
            $currentEmployee = Employee::where('user_id', '=', Auth::user()->id)->first();
            $query->leftJoin('announcement_employees', 'announcements.id', '=', 'announcement_employees.announcement_id')
                ->where('announcement_employees.employee_id', '=', $currentEmployee->id)
                ->orWhere(function ($q) {
                    $q->where('announcements.department_id', '["0"]')->where('announcements.employee_id', '["0"]');
                });
        }

        $announcements = $query->orderBy('start_date', 'desc')->get();

        return $this->success(['announcements' => $announcements], 'Announcements by date range retrieved.');
    }

    public function getAnnouncementStats()
    {
        $query = Announcement::where('created_by', Auth::user()->creatorId());

        if (Auth::user()->type == 'Employee') {
            $currentEmployee = Employee::where('user_id', '=', Auth::user()->id)->first();
            $query->leftJoin('announcement_employees', 'announcements.id', '=', 'announcement_employees.announcement_id')
                ->where('announcement_employees.employee_id', '=', $currentEmployee->id)
                ->orWhere(function ($q) {
                    $q->where('announcements.department_id', '["0"]')->where('announcements.employee_id', '["0"]');
                });
        }

        $totalAnnouncements = $query->count();
        $activeAnnouncements = $query->where('start_date', '<=', date('Y-m-d'))
            ->where('end_date', '>=', date('Y-m-d'))
            ->count();
        $expiredAnnouncements = $query->where('end_date', '<', date('Y-m-d'))->count();

        // Group by branch
        $announcementsByBranch = Announcement::where('created_by', Auth::user()->creatorId())
            ->with('branches')
            ->get()
            ->groupBy('branches.name')
            ->map(function ($group) {
                return [
                    'branch_name' => $group->first()->branches->name,
                    'announcement_count' => $group->count(),
                ];
            });

        // Group by month
        $announcementsByMonth = $query->get()
            ->groupBy(function ($announcement) {
                return date('Y-m', strtotime($announcement->start_date));
            })
            ->map(function ($group) {
                return $group->count();
            });

        return $this->success([
            'total_announcements' => $totalAnnouncements,
            'active_announcements' => $activeAnnouncements,
            'expired_announcements' => $expiredAnnouncements,
            'announcements_by_branch' => $announcementsByBranch,
            'announcements_by_month' => $announcementsByMonth
        ], 'Announcement statistics retrieved.');
    }

    public function getRecentAnnouncements($limit = 10)
    {
        $query = Announcement::where('created_by', Auth::user()->creatorId());

        if (Auth::user()->type == 'Employee') {
            $currentEmployee = Employee::where('user_id', '=', Auth::user()->id)->first();
            $query->leftJoin('announcement_employees', 'announcements.id', '=', 'announcement_employees.announcement_id')
                ->where('announcement_employees.employee_id', '=', $currentEmployee->id)
                ->orWhere(function ($q) {
                    $q->where('announcements.department_id', '["0"]')->where('announcements.employee_id', '["0"]');
                });
        }

        $announcements = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $this->success(['announcements' => $announcements], 'Recent announcements retrieved.');
    }

    public function searchAnnouncements(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $query = Announcement::where('created_by', Auth::user()->creatorId())
            ->where(function ($q) use ($request) {
                $q->where('title', 'LIKE', "%{$request->query}%")
                    ->orWhere('description', 'LIKE', "%{$request->query}%");
            });

        if (Auth::user()->type == 'Employee') {
            $currentEmployee = Employee::where('user_id', '=', Auth::user()->id)->first();
            $query->leftJoin('announcement_employees', 'announcements.id', '=', 'announcement_employees.announcement_id')
                ->where('announcement_employees.employee_id', '=', $currentEmployee->id)
                ->orWhere(function ($q) {
                    $q->where('announcements.department_id', '["0"]')->where('announcements.employee_id', '["0"]');
                });
        }

        $announcements = $query->orderBy('created_at', 'desc')->get();

        return $this->success(['announcements' => $announcements], 'Search results retrieved.');
    }

    private function getTargetEmployees($branchId, $departmentIds, $employeeIds)
    {
        if (in_array('0', $employeeIds) && in_array('0', $departmentIds) && $branchId == "0") {
            // All employees
            return Employee::where('created_by', Auth::user()->creatorId())->pluck('id')->toArray();
        } elseif (in_array('0', $employeeIds) && in_array('0', $departmentIds) && $branchId != "0") {
            // All employees in specific branch
            return Employee::where('branch_id', $branchId)
                ->where('created_by', Auth::user()->creatorId())
                ->pluck('id')
                ->toArray();
        } elseif (in_array('0', $employeeIds) && !in_array('0', $departmentIds)) {
            // All employees in specific departments
            return Employee::whereIn('department_id', $departmentIds)
                ->where('created_by', Auth::user()->creatorId())
                ->pluck('id')
                ->toArray();
        } else {
            // Specific employees
            return $employeeIds;
        }
    }
}
