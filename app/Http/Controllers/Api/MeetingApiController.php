<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Meeting;
use App\Models\MeetingEmployee;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Utility;
use App\Models\ZoomMeeting;
use App\Traits\ZoomMeetingTrait;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MeetingApiController extends Controller
{
    use ApiResponser;
    use ZoomMeetingTrait;

    public function getMeetings(Request $request)
    {
        $perPage = (int) ($request->per_page ?? 15);

        if (Auth::user()->type == 'Employee') {
            $currentEmployee = Employee::where('user_id', '=', Auth::user()->id)->first();
            $query = Meeting::orderBy('meetings.id', 'desc')
                ->leftJoin('meeting_employees', 'meetings.id', '=', 'meeting_employees.meeting_id')
                ->where('meeting_employees.employee_id', '=', $currentEmployee->id)
                ->orWhere(function ($q) {
                    $q->where('meetings.department_id', '["0"]')
                        ->where('meetings.employee_id', '["0"]');
                })
                ->with(['meetingEmployees.employee'])
                ->select('meetings.*');
        } else {
            $query = Meeting::where('meetings.created_by', '=', Auth::user()->creatorId())
                ->with(['meetingEmployees.employee'])
                ->select('meetings.*');
        }

        if ($request->search) {
            $s = $request->search;
            $query->where('meetings.title', 'like', "%{$s}%");
        } elseif ($request->title) {
            $query->where('meetings.title', 'like', "%{$request->title}%");
        }

        if ($request->branch_id)     $query->where('meetings.branch_id', $request->branch_id);
        if ($request->department_id) $query->where('meetings.department_id', 'like', "%{$request->department_id}%");

        if ($request->date_from && $request->date_to) {
            $query->whereBetween('meetings.date', [$request->date_from, $request->date_to]);
        } elseif ($request->date_from) {
            $query->where('meetings.date', '>=', $request->date_from);
        } elseif ($request->date_to) {
            $query->where('meetings.date', '<=', $request->date_to);
        }

        $result = $query->paginate($perPage);

        return $this->success([
            'meetings'   => $result->items(),
            'pagination' => [
                'total'        => $result->total(),
                'per_page'     => $result->perPage(),
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
            ],
        ], 'Meetings retrieved.');
    }

    public function createMeeting(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'employee_id' => 'required|array',
            'department_id' => 'required|array',
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'time' => 'required',
            'note' => 'nullable|string',
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

        $meeting = new Meeting();
        $meeting->branch_id = $request->branch_id;
        $meeting->department_id = json_encode($request->department_id);
        $meeting->employee_id = json_encode($request->employee_id);
        $meeting->title = $request->title;
        $meeting->date = $request->date;
        $meeting->time = $request->time;
        $meeting->note = $request->note;
        $meeting->created_by = Auth::user()->creatorId();
        $meeting->save();

        // Handle employee assignments
        if (in_array('0', $request->employee_id)) {
            $departmentEmployee = Employee::whereIn('department_id', $request->department_id)->get()->pluck('id');
            $departmentEmployee = $departmentEmployee;
        } else {
            $departmentEmployee = $request->employee_id;
        }

        foreach ($departmentEmployee as $employee) {
            $meetingEmployee = new MeetingEmployee();
            $meetingEmployee->meeting_id = $meeting->id;
            $meetingEmployee->employee_id = $employee;
            $meetingEmployee->created_by = Auth::user()->creatorId();
            $meetingEmployee->save();
        }

        // Send notifications
        $setting = Utility::settings(Auth::user()->creatorId());
        $meetingNotificationArr = [
            'meeting_title' => $request->title,
            'branch_name' => $branch->name,
            'meeting_date' => $request->date,
            'meeting_time' => $request->time,
        ];

        // Slack Notification
        if (isset($setting['meeting_notification']) && $setting['meeting_notification'] == 1) {
            Utility::send_slack_msg('new_meeting', $meetingNotificationArr);
        }

        // Telegram Notification
        if (isset($setting['telegram_meeting_notification']) && $setting['telegram_meeting_notification'] == 1) {
            Utility::send_telegram_msg('new_meeting', $meetingNotificationArr);
        }

        // Webhook
        $module = 'New Meeting';
        $webhook = Utility::webhookSetting($module);
        if ($webhook) {
            $parameter = json_encode($meetingEmployee);
            $status = Utility::WebhookCall($webhook['url'], $parameter, $webhook['method']);
        }

        return $this->success(['meeting' => $meeting], 'Meeting created successfully.');
    }

    public function getMeeting($id)
    {
        $meeting = Meeting::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with(['meetingEmployees.employee', 'branch'])
            ->first();

        if (!$meeting) {
            return $this->error('Meeting not found.', 404);
        }

        return $this->success(['meeting' => $meeting], 'Meeting detail.');
    }

    public function updateMeeting(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'time' => 'required',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $meeting = Meeting::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$meeting) {
            return $this->error('Meeting not found.', 404);
        }

        $meeting->title = $request->title;
        $meeting->date = $request->date;
        $meeting->time = $request->time;
        $meeting->note = $request->note;
        $meeting->save();

        return $this->success(['meeting' => $meeting], 'Meeting updated successfully.');
    }

    public function deleteMeeting($id)
    {
        $meeting = Meeting::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$meeting) {
            return $this->error('Meeting not found.', 404);
        }

        // Delete meeting employees
        MeetingEmployee::where('meeting_id', $id)->delete();
        $meeting->delete();

        return $this->success([], 'Meeting deleted successfully.');
    }

    public function createZoomMeeting(Request $request)
    {
        $settings = Utility::settings();

        if (
            !isset($settings['zoom_account_id']) || $settings['zoom_account_id'] == "" ||
            !isset($settings['zoom_client_id']) || $settings['zoom_client_id'] == "" ||
            !isset($settings['zoom_client_secret']) || $settings['zoom_client_secret'] == ''
        ) {
            return $this->error('Zoom integration not configured.', 400);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'start_date' => 'required|date',
            'duration' => 'required|integer|min:1',
            'project_id' => 'nullable|exists:projects,id',
            'attendees' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Check if project belongs to user's company
        if ($request->has('project_id')) {
            $project = Project::where('id', $request->project_id)
                ->where('created_by', Auth::user()->creatorId())
                ->first();

            if (!$project) {
                return $this->error('Project not found or access denied.', 404);
            }
        }

        $data['title'] = $request->title;
        $data['start_time'] = date('y:m:d H:i:s', strtotime($request->start_date));
        $data['duration'] = (int)$request->duration;
        $data['agenda'] = $request->agenda ?? '';
        $data['host_video'] = 1;
        $data->participant_video = 1;
        $data->waiting_room = 1;
        $data->join_before_host = 0;
        $data->jmt_time = 0;
        $data->meeting_type = $request->meeting_type ?? 2; // Default to scheduled
        $data->timezone = $settings['timezone'] ?? 'UTC';

        if ($request->has('project_id')) {
            $data['project_id'] = $request->project_id;
        }

        if ($request->has('attendees')) {
            $data['attendees'] = $request->attendees;
        }

        try {
            $zoomMeeting = $this->create($data);

            $zoomMeetingData = new ZoomMeeting();
            $zoomMeeting->meeting_id = $zoomMeeting->id;
            $zoomMeeting->topic = $zoomMeeting->topic;
            $zoomMeeting->agenda = $zoomMeeting->agenda;
            $zoomMeeting->start_time = $zoomMeeting->start_time;
            $zoomMeeting->start_url = $zoomMeeting->start_url;
            $zoomMeeting->join_url = $zoomMeeting->join_url;
            $zoomMeeting->duration = $zoomMeeting->duration;
            $zoomMeeting->password = $zoomMeeting->password;
            $zoomMeeting->host_id = $zoomMeeting->host_id;
            $zoomMeeting->start_url = $zoomMeeting->start_url;
            $zoomMeeting->join_url = $zoomMeeting->join_url;
            $zoomMeeting->created_by = Auth::user()->creatorId();
            $zoomMeeting->project_id = $request->project_id ?? null;

            $zoomMeeting->save();

            return $this->success(['zoom_meeting' => $zoomMeeting], 'Zoom meeting created successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to create Zoom meeting: ' . $e->getMessage(), 500);
        }
    }

    public function getZoomMeetings()
    {
        $settings = Utility::settings();

        if (
            !isset($settings['zoom_account_id']) || $settings['zoom_account_id'] == "" ||
            !isset($settings['zoom_client_id']) || $settings['zoom_client_id'] == "" ||
            !isset($settings['zoom_client_secret']) || $settings['zoom_client_secret'] == ''
        ) {
            return $this->error('Zoom integration not configured.', 400);
        }

        if (Auth::user()->isClient()) {
            $meetings = ZoomMeeting::where('client_id', Auth::user()->id)
                ->with(['projectName'])
                ->get();
        } else {
            $meetings = ZoomMeeting::where('created_by', Auth::user()->creatorId())
                ->with(['projectName'])
                ->get();
        }

        return $this->success(['zoom_meetings' => $meetings], 'Zoom meetings retrieved.');
    }

    public function getBranches()
    {
        $branches = Branch::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['branches' => $branches], 'Branches retrieved.');
    }

    public function getDepartments($branchId = null)
    {
        $query = Department::where('created_by', Auth::user()->creatorId());

        if ($branchId && $branchId != '0') {
            $query->where('branch_id', $branchId);
        }

        $departments = $query->get();
        return $this->success(['departments' => $departments], 'Departments retrieved.');
    }

    public function getEmployees($departmentId = null)
    {
        $query = Employee::where('created_by', Auth::user()->creatorId());

        if ($departmentId && !in_array('0', $departmentId)) {
            $query->whereIn('department_id', $departmentId);
        }

        $employees = $query->get();
        return $this->success(['employees' => $employees], 'Employees retrieved.');
    }
}
