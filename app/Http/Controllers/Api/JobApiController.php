<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\JobCategory;
use App\Models\Branch;
use App\Models\CustomQuestion;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class JobApiController extends Controller
{
    use ApiResponser;

    public function getJobs()
    {
        $jobs = Job::where('created_by', Auth::user()->creatorId())->with(['branches', 'createdBy'])->get();
        return $this->success(['jobs' => $jobs], 'Jobs retrieved.');
    }

    public function createJob(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'branch' => 'required',
            'category' => 'required',
            'skill' => 'required',
            'position' => 'required|integer',
            'start_date' => 'required',
            'end_date' => 'required',
            'description' => 'required',
            'requirement' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $job = new Job();
        $job->title = $request->title;
        $job->branch = $request->branch;
        $job->category = $request->category;
        $job->skill = is_array($request->skill) ? implode(',', $request->skill) : $request->skill;
        $job->position = $request->position;
        $job->status = $request->status ?? 'active';
        $job->start_date = $request->start_date;
        $job->end_date = $request->end_date;
        $job->description = $request->description;
        $job->requirement = $request->requirement;
        $job->code = uniqid();
        $job->applicant = !empty($request->applicant) ? implode(',', $request->applicant) : '';
        $job->visibility = !empty($request->visibility) ? implode(',', $request->visibility) : '';
        $job->custom_question = !empty($request->custom_question) ? implode(',', $request->custom_question) : '';
        $job->created_by = Auth::user()->creatorId();
        $job->save();

        return $this->success(['job' => $job], 'Job created successfully.');
    }

    public function getJob($id)
    {
        $job = Job::where('id', $id)->where('created_by', Auth::user()->creatorId())->with(['branches', 'createdBy'])->first();
        if (!$job) {
            return $this->error('Job not found.', 404);
        }
        return $this->success(['job' => $job], 'Job detail.');
    }

    public function updateJob(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'branch' => 'required',
            'category' => 'required',
            'skill' => 'required',
            'position' => 'required|integer',
            'start_date' => 'required',
            'end_date' => 'required',
            'description' => 'required',
            'requirement' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $job = Job::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$job) {
            return $this->error('Job not found.', 404);
        }

        $job->title = $request->title;
        $job->branch = $request->branch;
        $job->category = $request->category;
        $job->skill = is_array($request->skill) ? implode(',', $request->skill) : $request->skill;
        $job->position = $request->position;
        $job->status = $request->status;
        $job->start_date = $request->start_date;
        $job->end_date = $request->end_date;
        $job->description = $request->description;
        $job->requirement = $request->requirement;
        $job->applicant = !empty($request->applicant) ? implode(',', $request->applicant) : '';
        $job->visibility = !empty($request->visibility) ? implode(',', $request->visibility) : '';
        $job->custom_question = !empty($request->custom_question) ? implode(',', $request->custom_question) : '';
        $job->save();

        return $this->success(['job' => $job], 'Job updated successfully.');
    }

    public function deleteJob($id)
    {
        $job = Job::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$job) {
            return $this->error('Job not found.', 404);
        }
        // Delete related applications and notes
        $application = JobApplication::where('job', $job->id)->get()->pluck('id');
        \App\Models\JobApplicationNote::whereIn('application_id', $application)->delete();
        JobApplication::where('job', $job->id)->delete();
        $job->delete();
        return $this->success([], 'Job deleted successfully.');
    }

    public function getJobApplications($id)
    {
        $job = Job::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$job) {
            return $this->error('Job not found.', 404);
        }
        $applications = JobApplication::where('job', $id)->with(['user'])->get();
        return $this->success(['applications' => $applications], 'Applications retrieved.');
    }

    public function getJobCategories()
    {
        $categories = JobCategory::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['categories' => $categories], 'Categories retrieved.');
    }

    public function getJobBranches()
    {
        $branches = Branch::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['branches' => $branches], 'Branches retrieved.');
    }

    public function getCustomQuestions()
    {
        $questions = CustomQuestion::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['questions' => $questions], 'Custom questions retrieved.');
    }
}
