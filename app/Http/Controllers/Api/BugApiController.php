<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Bug;
use App\Models\BugStatus;
use App\Models\BugComment;
use App\Models\BugFile;
use App\Models\ProjectUser;
use App\Models\User;
use App\Models\ActivityLog;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BugApiController extends Controller
{
    use ApiResponser;

    public function getBugs()
    {
        $usr = Auth::user();
        if ($usr->type == 'company') {
            $bugs = Bug::where('created_by', $usr->creatorId())->with(['project', 'createdBy', 'status'])->get();
        } elseif ($usr->type == 'client') {
            $user_projects = \App\Models\Project::where('client_id', $usr->id)->pluck('id', 'id')->toArray();
            $bugs = Bug::whereIn('project_id', $user_projects)->where('created_by', $usr->creatorId())->with(['project', 'createdBy'])->get();
        } else {
            $bugs = Bug::where('created_by', $usr->creatorId())->whereRaw("find_in_set('" . $usr->id . "',assign_to)")->with(['project', 'createdBy'])->get();
        }
        return $this->success(['bugs' => $bugs], 'Bugs retrieved.');
    }

    public function createBug(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'priority' => 'required',
            'status' => 'required',
            'assign_to' => 'required',
            'start_date' => 'required',
            'due_date' => 'required',
            'project_id' => 'required|exists:projects,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $bug = new Bug();
        $bug->bug_id = $this->bugNumber();
        $bug->project_id = $request->project_id;
        $bug->title = $request->title;
        $bug->priority = $request->priority;
        $bug->start_date = $request->start_date;
        $bug->due_date = $request->due_date;
        $bug->description = $request->description;
        $bug->status = $request->status;
        $bug->assign_to = $request->assign_to;
        $bug->created_by = Auth::user()->creatorId();
        $bug->save();

        ActivityLog::create([
            'user_id' => Auth::user()->id,
            'project_id' => $request->project_id,
            'log_type' => 'Create Bug',
            'remark' => json_encode(['title' => $bug->title]),
        ]);

        return $this->success(['bug' => $bug], 'Bug created successfully.');
    }

    public function getBug($id)
    {
        $bug = Bug::with(['project', 'createdBy', 'status', 'comments', 'files'])->find($id);
        if (!$bug) {
            return $this->error('Bug not found.', 404);
        }
        return $this->success(['bug' => $bug], 'Bug detail.');
    }

    public function updateBug(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'priority' => 'required',
            'status' => 'required',
            'assign_to' => 'required',
            'start_date' => 'required',
            'due_date' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $bug = Bug::find($id);
        if (!$bug) {
            return $this->error('Bug not found.', 404);
        }

        $bug->title = $request->title;
        $bug->priority = $request->priority;
        $bug->start_date = $request->start_date;
        $bug->due_date = $request->due_date;
        $bug->description = $request->description;
        $bug->status = $request->status;
        $bug->assign_to = $request->assign_to;
        $bug->save();

        return $this->success(['bug' => $bug], 'Bug updated successfully.');
    }

    public function deleteBug($id)
    {
        $bug = Bug::find($id);
        if (!$bug) {
            return $this->error('Bug not found.', 404);
        }
        $bug->delete();
        return $this->success([], 'Bug deleted successfully.');
    }

    public function addBugComment(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'comment' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $bug = Bug::find($id);
        if (!$bug) {
            return $this->error('Bug not found.', 404);
        }

        $comment = new BugComment();
        $comment->bug_id = $id;
        $comment->comment = $request->comment;
        $comment->user_id = Auth::user()->id;
        $comment->created_by = Auth::user()->creatorId();
        $comment->save();

        return $this->success(['comment' => $comment], 'Comment added successfully.');
    }

    public function getBugComments($id)
    {
        $bug = Bug::find($id);
        if (!$bug) {
            return $this->error('Bug not found.', 404);
        }

        $comments = BugComment::where('bug_id', $id)->with('user')->get();
        return $this->success(['comments' => $comments], 'Comments retrieved.');
    }

    public function getBugStatuses()
    {
        $statuses = BugStatus::where('created_by', Auth::user()->creatorId())->orderBy('order')->get();
        return $this->success(['statuses' => $statuses], 'Bug statuses retrieved.');
    }

    public function updateBugStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status_id' => 'required|exists:bug_statuses,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $bug = Bug::find($id);
        if (!$bug) {
            return $this->error('Bug not found.', 404);
        }

        $bug->status = $request->status_id;
        $bug->save();

        return $this->success(['bug' => $bug], 'Bug status updated successfully.');
    }

    function bugNumber()
    {
        $latest = Bug::where('created_by', '=', Auth::user()->creatorId())->latest('bug_id')->first();
        if (!$latest) {
            return 1;
        }
        return $latest->bug_id + 1;
    }
}
