<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JobApplication;
use App\Models\Job;
use App\Models\JobStage;
use App\Models\JobApplicationNote;
use App\Models\CustomQuestion;
use App\Models\Utility;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class JobApplicationApiController extends Controller
{
    use ApiResponser;

    public function getJobApplications()
    {
        $applications = JobApplication::where('created_by', Auth::user()->creatorId())
            ->with(['job', 'stage'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success(['applications' => $applications], 'Job Applications retrieved.');
    }

    public function getJobApplication($id)
    {
        $application = JobApplication::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with(['job', 'stage', 'notes'])
            ->first();

        if (!$application) {
            return $this->error('Job application not found.', 404);
        }

        return $this->success(['application' => $application], 'Job application detail.');
    }

    public function updateJobApplication(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'required|string|max:20',
            'cover_letter' => 'nullable|string',
            'dob' => 'nullable|date',
            'gender' => 'nullable|string',
            'country' => 'nullable|string',
            'state' => 'nullable|string',
            'city' => 'nullable|string',
            'skill' => 'nullable|string',
            'rating' => 'nullable|integer|min:1|max:5',
            'stage' => 'nullable|exists:job_stages,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $application = JobApplication::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$application) {
            return $this->error('Job application not found.', 404);
        }

        $application->name = $request->name;
        $application->email = $request->email;
        $application->phone = $request->phone;
        $application->cover_letter = $request->cover_letter;
        $application->dob = $request->dob;
        $application->gender = $request->gender;
        $application->country = $request->country;
        $application->state = $request->state;
        $application->city = $request->city;
        $application->skill = $request->skill;

        if ($request->has('rating')) {
            $application->rating = $request->rating;
        }

        if ($request->has('stage')) {
            $application->stage = $request->stage;
        }

        $application->save();

        return $this->success(['application' => $application], 'Job application updated successfully.');
    }

    public function deleteJobApplication($id)
    {
        $application = JobApplication::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$application) {
            return $this->error('Job application not found.', 404);
        }

        // Delete associated files and update storage limit
        if (!empty($application->profile)) {
            $file_path = 'uploads/job/profile/' . $application->profile;
            Utility::changeStorageLimit(Auth::user()->creatorId(), $file_path);
        }

        if (!empty($application->resume)) {
            $file_path = 'uploads/job/resume/' . $application->resume;
            Utility::changeStorageLimit(Auth::user()->creatorId(), $file_path);
        }

        $application->delete();

        return $this->success([], 'Job application deleted successfully.');
    }

    public function moveApplicationStage(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'stage_id' => 'required|exists:job_stages,id',
            'order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $application = JobApplication::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$application) {
            return $this->error('Job application not found.', 404);
        }

        // Check if stage belongs to user's company
        $stage = JobStage::where('id', $request->stage_id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$stage) {
            return $this->error('Stage not found or access denied.', 404);
        }

        $application->stage = $request->stage_id;

        if ($request->has('order')) {
            $application->order = $request->order;
        }

        $application->save();

        return $this->success(['application' => $application], 'Application stage moved successfully.');
    }

    public function addApplicationSkill(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'skill' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $application = JobApplication::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$application) {
            return $this->error('Job application not found.', 404);
        }

        $application->skill = $request->skill;
        $application->save();

        return $this->success(['application' => $application], 'Skill added successfully.');
    }

    public function addApplicationNote(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'note' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $application = JobApplication::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$application) {
            return $this->error('Job application not found.', 404);
        }

        $note = new JobApplicationNote();
        $note->application_id = $id;
        $note->note = $request->note;
        $note->note_created = Auth::user()->id;
        $note->created_by = Auth::user()->creatorId();
        $note->save();

        return $this->success(['note' => $note], 'Note added successfully.');
    }

    public function deleteApplicationNote($noteId)
    {
        $note = JobApplicationNote::find($noteId);

        if (!$note) {
            return $this->error('Note not found.', 404);
        }

        // Check if note belongs to user's company
        $application = JobApplication::where('id', $note->application_id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$application) {
            return $this->error('Access denied.', 403);
        }

        $note->delete();

        return $this->success([], 'Note deleted successfully.');
    }

    public function rateApplication(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $application = JobApplication::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$application) {
            return $this->error('Job application not found.', 404);
        }

        $application->rating = $request->rating;
        $application->save();

        return $this->success(['application' => $application], 'Application rated successfully.');
    }

    public function archiveApplication($id)
    {
        $application = JobApplication::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$application) {
            return $this->error('Job application not found.', 404);
        }

        $application->is_archive = $application->is_archive == 0 ? 1 : 0;
        $application->save();

        $status = $application->is_archive == 1 ? 'archived' : 'unarchived';

        return $this->success(['application' => $application], "Application {$status} successfully.");
    }

    public function getArchivedApplications()
    {
        $applications = JobApplication::where('created_by', Auth::user()->creatorId())
            ->where('is_archive', 1)
            ->with(['job', 'stage'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success(['applications' => $applications], 'Archived applications retrieved.');
    }

    public function getJobs()
    {
        $jobs = Job::where('created_by', Auth::user()->creatorId())
            ->get(['id', 'title']);

        return $this->success(['jobs' => $jobs], 'Jobs retrieved.');
    }

    public function getStages()
    {
        $stages = JobStage::where('created_by', Auth::user()->creatorId())
            ->orderBy('order', 'asc')
            ->get();

        return $this->success(['stages' => $stages], 'Stages retrieved.');
    }

    public function getCustomQuestions()
    {
        $questions = CustomQuestion::where('created_by', Auth::user()->creatorId())
            ->get();

        return $this->success(['questions' => $questions], 'Custom questions retrieved.');
    }

    public function getApplicationStats()
    {
        $totalApplications = JobApplication::where('created_by', Auth::user()->creatorId())->count();
        $archivedApplications = JobApplication::where('created_by', Auth::user()->creatorId())
            ->where('is_archive', 1)
            ->count();
        $activeApplications = $totalApplications - $archivedApplications;

        // Group by stage
        $applicationsByStage = JobApplication::where('created_by', Auth::user()->creatorId())
            ->where('is_archive', 0)
            ->with('stage')
            ->get()
            ->groupBy('stage.name')
            ->map(function ($group) {
                return $group->count();
            });

        // Group by job
        $applicationsByJob = JobApplication::where('created_by', Auth::user()->creatorId())
            ->with('job')
            ->get()
            ->groupBy('job.title')
            ->map(function ($group) {
                return $group->count();
            });

        return $this->success([
            'total_applications' => $totalApplications,
            'active_applications' => $activeApplications,
            'archived_applications' => $archivedApplications,
            'applications_by_stage' => $applicationsByStage,
            'applications_by_job' => $applicationsByJob
        ], 'Application statistics retrieved.');
    }
}
