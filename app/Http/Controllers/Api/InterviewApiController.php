<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InterviewSchedule;
use App\Models\JobApplication;
use App\Models\User;
use App\Models\JobStage;
use App\Models\Utility;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class InterviewApiController extends Controller
{
    use ApiResponser;

    public function getInterviews()
    {
        $interviews = InterviewSchedule::where('created_by', Auth::user()->creatorId())
            ->with(['applications', 'interviewer'])
            ->orderBy('date', 'asc')
            ->get();

        return $this->success(['interviews' => $interviews], 'Interviews retrieved.');
    }

    public function scheduleInterview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'candidate' => 'required|exists:job_applications,id',
            'employee' => 'required|exists:users,id',
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required',
            'comment' => 'nullable|string',
            'synchronize_type' => 'nullable|string|in:google_calender',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Check if candidate belongs to user's company
        $candidate = JobApplication::where('id', $request->candidate)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$candidate) {
            return $this->error('Candidate not found or access denied.', 404);
        }

        // Check if employee belongs to user's company
        $employee = User::where('id', $request->employee)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$employee) {
            return $this->error('Interviewer not found or access denied.', 404);
        }

        $schedule = new InterviewSchedule();
        $schedule->candidate = $request->candidate;
        $schedule->employee = $request->employee;
        $schedule->date = $request->date;
        $schedule->time = $request->time;
        $schedule->comment = $request->comment;
        $schedule->created_by = Auth::user()->creatorId();
        $schedule->save();

        // Google Calendar Integration
        if ($request->get('synchronize_type') == 'google_calender') {
            $type = 'interview_schedule';
            $request1 = new InterviewSchedule();
            $request1->title = $request->comment;
            $request1->start_date = $request->date;
            $request1->end_date = $request->date;

            Utility::addCalendarData($request1, $type);
        }

        return $this->success(['interview' => $schedule], 'Interview scheduled successfully.');
    }

    public function getInterview($id)
    {
        $interview = InterviewSchedule::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with(['applications', 'interviewer'])
            ->first();

        if (!$interview) {
            return $this->error('Interview not found.', 404);
        }

        return $this->success(['interview' => $interview], 'Interview detail.');
    }

    public function updateInterview(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'candidate' => 'required|exists:job_applications,id',
            'employee' => 'required|exists:users,id',
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required',
            'comment' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $interview = InterviewSchedule::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$interview) {
            return $this->error('Interview not found.', 404);
        }

        // Check if candidate belongs to user's company
        $candidate = JobApplication::where('id', $request->candidate)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$candidate) {
            return $this->error('Candidate not found or access denied.', 404);
        }

        // Check if employee belongs to user's company
        $employee = User::where('id', $request->employee)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$employee) {
            return $this->error('Interviewer not found or access denied.', 404);
        }

        $interview->candidate = $request->candidate;
        $interview->employee = $request->employee;
        $interview->date = $request->date;
        $interview->time = $request->time;
        $interview->comment = $request->comment;
        $interview->save();

        return $this->success(['interview' => $interview], 'Interview updated successfully.');
    }

    public function deleteInterview($id)
    {
        $interview = InterviewSchedule::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$interview) {
            return $this->error('Interview not found.', 404);
        }

        $interview->delete();

        return $this->success([], 'Interview deleted successfully.');
    }

    public function getCandidates()
    {
        $candidates = JobApplication::where('created_by', Auth::user()->creatorId())
            ->get(['id', 'name']);

        return $this->success(['candidates' => $candidates], 'Candidates retrieved.');
    }

    public function getInterviewers()
    {
        $employees = User::where('created_by', Auth::user()->creatorId())
            ->where('type', 'employee')
            ->orWhere('id', Auth::user()->creatorId())
            ->get(['id', 'name']);

        return $this->success(['employees' => $employees], 'Interviewers retrieved.');
    }

    public function getCalendarInterviews(Request $request)
    {
        $interviews = InterviewSchedule::where('created_by', Auth::user()->creatorId())
            ->with(['applications', 'interviewer'])
            ->get();

        $calendarEvents = [];

        foreach ($interviews as $interview) {
            $title = !empty($interview->applications) ?
                (!empty($interview->applications->jobs) ? $interview->applications->jobs->title : '') :
                $interview->comment;

            $calendarEvents[] = [
                'id' => $interview->id,
                'title' => $title,
                'start' => $interview->date,
                'className' => 'event-primary',
                'url' => route('interview-schedule.show', $interview->id),
                'allDay' => true,
            ];
        }

        return $this->success(['events' => $calendarEvents], 'Calendar interviews retrieved.');
    }

    public function getGoogleCalendarInterviews(Request $request)
    {
        if ($request->get('calender_type') == 'google_calender') {
            $type = 'interview_schedule';
            $arrayJson = Utility::getCalendarData($type);
        } else {
            $data = InterviewSchedule::where('created_by', Auth::user()->creatorId())->get();
            $arrayJson = [];
            foreach ($data as $val) {
                $arrayJson[] = [
                    "id" => $val->id,
                    "title" => $val->comment,
                    "start" => $val->date . ' ' . $val->time,
                    "className" => 'event-primary',
                    "textColor" => '#51459d',
                    'url' => route('interview-schedule.show', $val->id),
                    "allDay" => false,
                ];
            }
        }

        return $this->success(['events' => $arrayJson], 'Google calendar interviews retrieved.');
    }

    public function getInterviewsByDateRange(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $interviews = InterviewSchedule::where('created_by', Auth::user()->creatorId())
            ->where('date', '>=', $request->start_date)
            ->where('date', '<=', $request->end_date)
            ->with(['applications', 'interviewer'])
            ->orderBy('date', 'asc')
            ->get();

        return $this->success(['interviews' => $interviews], 'Interviews by date range retrieved.');
    }

    public function getMyInterviews()
    {
        $interviews = InterviewSchedule::where('created_by', Auth::user()->creatorId())
            ->where('employee', Auth::user()->id)
            ->with(['applications'])
            ->orderBy('date', 'asc')
            ->get();

        return $this->success(['interviews' => $interviews], 'My interviews retrieved.');
    }

    public function getInterviewStats()
    {
        $totalInterviews = InterviewSchedule::where('created_by', Auth::user()->creatorId())->count();
        $upcomingInterviews = InterviewSchedule::where('created_by', Auth::user()->creatorId())
            ->where('date', '>=', date('Y-m-d'))
            ->count();
        $pastInterviews = InterviewSchedule::where('created_by', Auth::user()->creatorId())
            ->where('date', '<', date('Y-m-d'))
            ->count();

        // Group by interviewer
        $interviewsByInterviewer = InterviewSchedule::where('created_by', Auth::user()->creatorId())
            ->with('interviewer')
            ->get()
            ->groupBy('interviewer.name')
            ->map(function ($group) {
                return $group->count();
            });

        // Group by month
        $interviewsByMonth = InterviewSchedule::where('created_by', Auth::user()->creatorId())
            ->get()
            ->groupBy(function ($interview) {
                return date('Y-m', strtotime($interview->date));
            })
            ->map(function ($group) {
                return $group->count();
            });

        return $this->success([
            'total_interviews' => $totalInterviews,
            'upcoming_interviews' => $upcomingInterviews,
            'past_interviews' => $pastInterviews,
            'interviews_by_interviewer' => $interviewsByInterviewer,
            'interviews_by_month' => $interviewsByMonth
        ], 'Interview statistics retrieved.');
    }
}
