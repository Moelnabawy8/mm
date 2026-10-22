<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ActivityLog;
use App\Models\Utility;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MilestoneApiController extends Controller
{
    use ApiResponser;

    public function getMilestones()
    {
        $milestones = Milestone::whereHas('project', function ($query) {
            $query->where('created_by', Auth::user()->creatorId());
        })
            ->with('project')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success(['milestones' => $milestones], 'Milestones retrieved.');
    }

    public function getProjectMilestones($projectId)
    {
        $project = Project::where('id', $projectId)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$project) {
            return $this->error('Project not found or access denied.', 404);
        }

        $milestones = Milestone::where('project_id', $projectId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success(['milestones' => $milestones], 'Project milestones retrieved.');
    }

    public function createMilestone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'title' => 'required|string|max:255',
            'status' => 'required|string',
            'cost' => 'required|numeric|min:0',
            'due_date' => 'required|date',
            'start_date' => 'required|date',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Check if project belongs to user's company
        $project = Project::where('id', $request->project_id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$project) {
            return $this->error('Project not found or access denied.', 404);
        }

        $milestone = new Milestone();
        $milestone->project_id = $request->project_id;
        $milestone->title = $request->title;
        $milestone->status = $request->status;
        $milestone->cost = $request->cost;
        $milestone->start_date = $request->start_date;
        $milestone->due_date = $request->due_date;
        $milestone->description = $request->description;
        $milestone->save();

        // Create activity log
        ActivityLog::create([
            'user_id' => Auth::user()->id,
            'project_id' => $project->id,
            'log_type' => 'Create Milestone',
            'remark' => json_encode(['title' => $milestone->title]),
        ]);

        return $this->success(['milestone' => $milestone], 'Milestone created successfully.');
    }

    public function getMilestone($id)
    {
        $milestone = Milestone::where('id', $id)
            ->whereHas('project', function ($query) {
                $query->where('created_by', Auth::user()->creatorId());
            })
            ->with('project')
            ->first();

        if (!$milestone) {
            return $this->error('Milestone not found.', 404);
        }

        return $this->success(['milestone' => $milestone], 'Milestone detail.');
    }

    public function updateMilestone(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'status' => 'required|string',
            'cost' => 'required|numeric|min:0',
            'due_date' => 'required|date',
            'start_date' => 'required|date',
            'description' => 'nullable|string',
            'progress' => 'nullable|integer|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $milestone = Milestone::where('id', $id)
            ->whereHas('project', function ($query) {
                $query->where('created_by', Auth::user()->creatorId());
            })
            ->first();

        if (!$milestone) {
            return $this->error('Milestone not found.', 404);
        }

        $milestone->title = $request->title;
        $milestone->status = $request->status;
        $milestone->cost = $request->cost;
        $milestone->progress = $request->progress;
        $milestone->due_date = $request->due_date;
        $milestone->start_date = $request->start_date;
        $milestone->description = $request->description;
        $milestone->save();

        return $this->success(['milestone' => $milestone], 'Milestone updated successfully.');
    }

    public function deleteMilestone($id)
    {
        $milestone = Milestone::where('id', $id)
            ->whereHas('project', function ($query) {
                $query->where('created_by', Auth::user()->creatorId());
            })
            ->first();

        if (!$milestone) {
            return $this->error('Milestone not found.', 404);
        }

        $milestone->delete();

        return $this->success([], 'Milestone deleted successfully.');
    }

    public function getMilestoneStats($projectId = null)
    {
        $query = Milestone::query();

        if ($projectId) {
            // Check if project belongs to user's company
            $project = Project::where('id', $projectId)
                ->where('created_by', Auth::user()->creatorId())
                ->first();

            if (!$project) {
                return $this->error('Project not found or access denied.', 404);
            }

            $query->where('project_id', $projectId);
        } else {
            $query->whereHas('project', function ($query) {
                $query->where('created_by', Auth::user()->creatorId());
            });
        }

        $totalMilestones = $query->count();
        $completedMilestones = $query->where('status', 'LIKE', 'complete')->count();
        $inProgressMilestones = $query->where('status', 'LIKE', 'in_progress')->count();
        $notStartedMilestones = $query->where('status', 'LIKE', 'not_started')->count();

        $completionPercentage = $totalMilestones > 0 ? round(($completedMilestones / $totalMilestones) * 100, 2) : 0;

        return $this->success([
            'total_milestones' => $totalMilestones,
            'completed_milestones' => $completedMilestones,
            'in_progress_milestones' => $inProgressMilestones,
            'not_started_milestones' => $notStartedMilestones,
            'completion_percentage' => $completionPercentage
        ], 'Milestone statistics retrieved.');
    }

    public function getProjects()
    {
        $projects = Project::where('created_by', Auth::user()->creatorId())
            ->get(['id', 'project_name']);

        return $this->success(['projects' => $projects], 'Projects retrieved.');
    }
}
