<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EventEmployee;
use App\Models\Utility;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class EventApiController extends Controller
{
    use ApiResponser;

    public function getEvents()
    {
        $events = Event::where('created_by', Auth::user()->creatorId())
            ->with(['branch', 'eventEmployees.employee'])
            ->orderBy('start_date', 'asc')
            ->get();

        return $this->success(['events' => $events], 'Events retrieved.');
    }

    public function createEvent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'department_id' => 'required|array',
            'employee_id' => 'required|array',
            'title' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'color' => 'required|string|max:20',
            'description' => 'nullable|string',
            'synchronize_type' => 'nullable|string|in:google_calender',
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

        // Check if departments belong to user's company
        foreach ($request->department_id as $deptId) {
            if ($deptId != 0) {
                $department = Department::where('id', $deptId)
                    ->where('created_by', Auth::user()->creatorId())
                    ->first();
                if (!$department) {
                    return $this->error('Department not found or access denied.', 404);
                }
            }
        }

        // Check if employees belong to user's company
        foreach ($request->employee_id as $empId) {
            if ($empId != 0) {
                $employee = Employee::where('id', $empId)
                    ->where('created_by', Auth::user()->creatorId())
                    ->first();
                if (!$employee) {
                    return $this->error('Employee not found or access denied.', 404);
                }
            }
        }

        $event = new Event();
        $event->branch_id = $request->branch_id;
        $event->department_id = json_encode($request->department_id);
        $event->employee_id = json_encode($request->employee_id);
        $event->title = $request->title;
        $event->start_date = $request->start_date;
        $event->end_date = $event->end_date;
        $event->color = $request->color;
        $event->description = $request->description;
        $event->created_by = Auth::user()->creatorId();
        $event->save();

        // Handle employee assignments
        if (in_array('0', $request->employee_id)) {
            $departmentEmployee = Employee::whereIn('department_id', $request->department_id)
                ->get()
                ->pluck('id');
            $departmentEmployee = $departmentEmployee;
        } else {
            $departmentEmployee = $request->employee_id;
        }

        foreach ($departmentEmployee as $employee) {
            $eventEmployee = new EventEmployee();
            $eventEmployee->event_id = $event->id;
            $eventEmployee->employee_id = $employee;
            $eventEmployee->created_by = Auth::user()->creatorId();
            $eventEmployee->save();
        }

        // Send notifications
        $setting = Utility::settings(Auth::user()->creatorId());

        if ($request->branch_id == 0) {
            $branch = Branch::all()->pluck('name');
            $result = '';
            $separator = ',';
            foreach ($branch as $value) {
                if (is_array($value)) {
                    $result .= arrayToString($value, $separator) . $separator;
                } else {
                    $result .= $value . $separator;
                }
            }
            $result = rtrim($result, $separator);
        } else {
            $branch = Branch::find($request->branch_id);
            $result = $branch->name;
        }

        $eventNotificationArr = [
            'event_title' => $request->title,
            'branch_name' => $result,
            'event_start_date' => $request->start_date,
            'event_end_date' => $request->end_date,
        ];

        // Slack Notification
        if (isset($setting['event_notification']) && $setting['event_notification'] == 1) {
            Utility::send_slack_msg('new_event', $eventNotificationArr);
        }

        // Telegram Notification
        if (isset($setting['telegram_event_notification']) && $setting['telegram_event_notification'] == 1) {
            Utility::send_telegram_msg('new_event', $eventNotificationArr);
        }

        // Google Calendar Integration
        if ($request->get('synchronize_type') == 'google_calender') {
            $type = 'event';
            Utility::addCalendarData($request, $type);
        }

        // Webhook
        $module = 'New Event';
        $webhook = Utility::webhookSetting($module);
        if ($webhook) {
            $parameter = json_encode($event);
            $status = Utility::WebhookCall($webhook['url'], $parameter, $webhook['method']);
        }

        return $this->success(['event' => $event], 'Event created successfully.');
    }

    public function getEvent($id)
    {
        $event = Event::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with(['branch', 'eventEmployees.employee'])
            ->first();

        if (!$event) {
            return $this->error('Event not found.', 404);
        }

        return $this->success(['event' => $event], 'Event detail.');
    }

    public function updateEvent(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'color' => 'required|string|max:20',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $event = Event::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$event) {
            return $this->error('Event not found.', 404);
        }

        $event->title = $request->title;
        $event->start_date = $request->start_date;
        $event->end_date = $request->end_date;
        $event->color = $request->color;
        $event->description = $request->description;
        $event->save();

        return $this->success(['event' => $event], 'Event updated successfully.');
    }

    public function deleteEvent($id)
    {
        $event = Event::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$event) {
            return $this->error('Event not found.', 404);
        }

        // Delete event employees
        EventEmployee::where('event_id', $id)->delete();
        $event->delete();

        return $this->success([], 'Event deleted successfully.');
    }

    public function getCalendarEvents(Request $request)
    {
        $events = Event::where('created_by', Auth::user()->creatorId());

        if (!empty($request->start_date)) {
            $events->where('date', '>=', $request->start_date);
        }

        if (!empty($request->end_date)) {
            $events->where('date', '<=', $request->end_date);
        }

        $events = $events->get();

        $calendarEvents = [];

        foreach ($events as $event) {
            $calendarEvents[] = [
                'id' => $event->id,
                'title' => $event->title,
                'start' => $event->start_date,
                'end' => $event->end_date,
                'className' => $event->color,
                'url' => route('event.edit', $event->id),
                'allDay' => true,
            ];
        }

        return $this->success(['events' => $calendarEvents], 'Calendar events retrieved.');
    }

    public function getGoogleCalendarEvents(Request $request)
    {
        if ($request->get('calender_type') == 'google_calender') {
            $type = 'event';
            $arrayJson = Utility::getCalendarData($type);
        } else {
            $data = Event::where('created_by', Auth::user()->creatorId())->get();
            $arrayJson = [];
            foreach ($data as $val) {
                $end_date = date_create($val->end_date);
                date_add($end_date, date_interval_create_from_date_string("1 days"));
                $arrayJson[] = [
                    "id" => $val->id,
                    "title" => $val->title,
                    "start" => $val->start_date,
                    "end" => date_format($end_date, "Y-m-d H:i:s"),
                    "className" => $val->color,
                    'url' => route('event.edit', $val->id),
                    "allDay" => true,
                ];
            }
        }

        return $this->success(['events' => $arrayJson], 'Google calendar events retrieved.');
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

    public function getCurrentMonthEvents()
    {
        $todayDate = date('m');
        $currentMonthEvents = Event::select('id', 'start_date', 'end_date', 'title', 'created_at', 'color')
            ->whereRaw('MONTH(start_date)=' . $todayDate)
            ->whereRaw('MONTH(end_date)=' . $todayDate)
            ->where('created_by', Auth::user()->creatorId())
            ->orderBy('start_date', 'asc')
            ->get();

        return $this->success(['events' => $currentMonthEvents], 'Current month events retrieved.');
    }
}
