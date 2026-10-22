<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Timesheet;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Utility;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TimesheetApiController extends Controller
{
    use ApiResponser;

    public function getTimesheets()
    {
        $timesheets = Timesheet::where('created_by', Auth::user()->creatorId())
            ->with(['project', 'task'])
            ->orderBy('date', 'desc')
            ->get();

        return $this->success(['timesheets' => $timesheets], 'Timesheets retrieved.');
    }

    public function createTimesheet(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'task_id' => 'required|exists:project_tasks,id',
            'date' => 'required|date',
            'time_hour' => 'required|integer|min:0|max:23',
            'time_minute' => 'required|integer|min:0|max:59',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Check if user has access to the project
        $project = Project::where('id', $request->project_id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$project) {
            return $this->error('Project not found or access denied.', 404);
        }

        // Check if task belongs to the project
        $task = ProjectTask::where('id', $request->task_id)
            ->where('project_id', $request->project_id)
            ->first();

        if (!$task) {
            return $this->error('Task not found in this project.', 404);
        }

        $hour = $request->time_hour;
        $minute = $request->time_minute;
        $time = ($hour != '' ? ($hour < 10 ? '0' . $hour : $hour) : '00') . ':' . ($minute != '' ? ($minute < 10 ? '0' . $minute : $minute) : '00');

        $timesheet = new Timesheet();
        $timesheet->project_id = $request->project_id;
        $timesheet->task_id = $request->task_id;
        $timesheet->date = $request->date;
        $timesheet->time = $time;
        $timesheet->description = $request->description;
        $timesheet->created_by = Auth::user()->creatorId();
        $timesheet->save();

        return $this->success(['timesheet' => $timesheet], 'Timesheet created successfully.');
    }

    public function getTimesheet($id)
    {
        $timesheet = Timesheet::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with(['project', 'task'])
            ->first();

        if (!$timesheet) {
            return $this->error('Timesheet not found.', 404);
        }

        return $this->success(['timesheet' => $timesheet], 'Timesheet detail.');
    }

    public function updateTimesheet(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'time_hour' => 'required|integer|min:0|max:23',
            'time_minute' => 'required|integer|min:0|max:59',
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $timesheet = Timesheet::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$timesheet) {
            return $this->error('Timesheet not found.', 404);
        }

        $hour = $request->time_hour;
        $minute = $request->time_minute;
        $time = ($hour != '' ? ($hour < 10 ? '0' . $hour : $hour) : '00') . ':' . ($minute != '' ? ($minute < 10 ? '0' . $minute : $minute) : '00');

        $timesheet->date = $request->date;
        $timesheet->time = $time;
        $timesheet->description = $request->description;
        $timesheet->save();

        return $this->success(['timesheet' => $timesheet], 'Timesheet updated successfully.');
    }

    public function deleteTimesheet($id)
    {
        $timesheet = Timesheet::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$timesheet) {
            return $this->error('Timesheet not found.', 404);
        }

        $timesheet->delete();

        return $this->success([], 'Timesheet deleted successfully.');
    }

    public function getProjectTimesheets($projectId)
    {
        $project = Project::where('id', $projectId)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$project) {
            return $this->error('Project not found or access denied.', 404);
        }

        $timesheets = Timesheet::where('project_id', $projectId)
            ->where('created_by', Auth::user()->creatorId())
            ->with(['task'])
            ->orderBy('date', 'desc')
            ->get();

        return $this->success(['timesheets' => $timesheets], 'Project timesheets retrieved.');
    }

    public function getWeeklyTimesheets(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'week' => 'required|string',
            'project_id' => 'nullable|exists:projects,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $week = $request->week;
        $projectId = $request->project_id;

        // Get user's projects
        $authuser = Auth::user();
        $projectIds = $authuser->projects()->pluck('project_id')->toArray();

        $timesheets = Timesheet::select('timesheets.*')
            ->join('projects', 'projects.id', '=', 'timesheets.project_id')
            ->join('project_tasks', 'project_tasks.id', '=', 'timesheets.task_id');

        if ($projectId == '0') {
            $timesheets = $timesheets->whereIn('projects.id', $projectIds);
        } else if (in_array($projectId, $projectIds)) {
            $timesheets = $timesheets->where('timesheets.project_id', $projectId);
        } else {
            return $this->error('Project not found or access denied.', 404);
        }

        $days = Utility::getFirstSeventhWeekDay($week);
        $firstDay = $days['first_day'];
        $seventhDay = $days['seventh_day'];

        $timesheets = $timesheets
            ->whereDate('date', '>=', $firstDay->format('Y-m-d'))
            ->whereDate('date', '<=', $seventhDay->format('Y-m-d'))
            ->where('timesheets.created_by', Auth::user()->creatorId())
            ->with(['project', 'task'])
            ->get();

        return $this->success([
            'timesheets' => $timesheets,
            'week_range' => $firstDay->format('M d') . ' - ' . $seventhDay->format('M d, Y'),
            'days' => $days
        ], 'Weekly timesheets retrieved.');
    }

    public function getProjects()
    {
        $authuser = Auth::user();
        $projects = $authuser->projects()->with('project')->get()->pluck('project', 'project_id');

        return $this->success(['projects' => $projects], 'Projects retrieved.');
    }

    public function getProjectTasks($projectId)
    {
        $project = Project::where('id', $projectId)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$project) {
            return $this->error('Project not found or access denied.', 404);
        }

        $tasks = ProjectTask::where('project_id', $projectId)->get();

        return $this->success(['tasks' => $tasks], 'Project tasks retrieved.');
    }
}
