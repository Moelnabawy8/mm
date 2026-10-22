<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\ProjectUser;
use App\Models\User;
use App\Models\ProjectTask;
use App\Models\Milestone;
use App\Models\Bug;
use App\Models\Expense;
use App\Models\ActivityLog;
use App\Models\Utility;
use App\Models\Department;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ProjectApiController extends Controller
{
    use ApiResponser;

    public function getProjects()
    {
        $usr = Auth::user();
        if ($usr->type == 'client') {
            $projects = Project::where('client_id', $usr->id)->get();
        } elseif ($usr->type == 'company') {
            $projects = Project::where('created_by', $usr->creatorId())->get();
        } else {
            $projects = Project::select('projects.*')->join('project_users', 'projects.id', '=', 'project_users.project_id')->where('project_users.user_id', '=', $usr->id)->get();
        }

        return $this->success(['projects' => $projects], 'Projects retrieved.');
    }

    public function createProject(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_name' => 'required',
            'department_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $project = new Project();
        $project->project_name = $request->project_name;
        $project->start_date = date("Y-m-d H:i:s", strtotime($request->start_date));
        $project->end_date = isset($request->end_date) ? date("Y-m-d H:i:s", strtotime($request->end_date)) : null;

        if ($request->has('project_image') && !empty($request->project_image)) {
            // Assuming base64 or file upload. For API, file upload is standard.
            if ($request->hasFile('project_image')) {
                $imageName = time() . '.' . $request->file('project_image')->extension();
                $request->file('project_image')->storeAs('projects', $imageName);
                $project->project_image = 'projects/' . $imageName;
            }
        }

        $project->department_id = $request->department_id ?? 0;
        $project->client_id = $request->client_id ?? 0;
        $project->budget = !empty($request->budget) ? $request->budget : 0;
        $project->description = $request->description;
        $project->status = $request->status ?? 'in_progress';
        $project->estimated_hrs = $request->estimated_hrs ?? 0;
        $project->tags = $request->tags;
        $project->created_by = Auth::user()->creatorId();
        $project->copylinksetting = '{"member":"on","milestone":"off","basic_details":"on","activity":"off","attachment":"on","bug_report":"on","task":"off","tracker_details":"off","timesheet":"off" ,"password_protected":"off"}';

        $project->save();

        if (Auth::user()->type == 'company') {
            ProjectUser::create(['project_id' => $project->id, 'user_id' => Auth::user()->id]);
        } else {
            ProjectUser::create(['project_id' => $project->id, 'user_id' => Auth::user()->creatorId()]);
            ProjectUser::create(['project_id' => $project->id, 'user_id' => Auth::user()->id]);
        }

        if ($request->has('users') && is_array($request->users)) {
            foreach ($request->users as $user_id) {
                ProjectUser::create(['project_id' => $project->id, 'user_id' => $user_id]);
            }
        }

        return $this->success(['project' => $project], 'Project created successfully.');
    }

    public function getProject($id)
    {
        $project = Project::with(['users', 'tasks', 'milestones'])->find($id);
        if (!$project) return $this->error('Project not found', 404);
        return $this->success(['project' => $project], 'Project detail.');
    }

    public function updateProject(Request $request, $id)
    {
        $project = Project::find($id);
        if (!$project) return $this->error('Project not found', 404);

        $validator = Validator::make($request->all(), [
            'project_name' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $project->project_name = $request->project_name;
        if ($request->has('start_date')) $project->start_date = date("Y-m-d H:i:s", strtotime($request->start_date));
        if ($request->has('end_date')) $project->end_date = date("Y-m-d H:i:s", strtotime($request->end_date));
        if ($request->has('budget')) $project->budget = $request->budget;
        if ($request->has('description')) $project->description = $request->description;
        if ($request->has('status')) $project->status = $request->status;
        if ($request->has('estimated_hrs')) $project->estimated_hrs = $request->estimated_hrs;
        if ($request->has('tags')) $project->tags = $request->tags;
        if ($request->has('client_id')) $project->client_id = $request->client_id;
        if ($request->has('department_id')) $project->department_id = $request->department_id;

        if ($request->hasFile('project_image')) {
            $imageName = time() . '.' . $request->file('project_image')->extension();
            $request->file('project_image')->storeAs('projects', $imageName);
            $project->project_image = 'projects/' . $imageName;
        }

        $project->save();
        return $this->success(['project' => $project], 'Project updated successfully.');
    }

    public function deleteProject($id)
    {
        $project = Project::find($id);
        if (!$project) return $this->error('Project not found', 404);

        $project->delete();
        return $this->success([], 'Project deleted successfully.');
    }

    public function duplicateProject(Request $request, $id)
    {
        $project = Project::find($id);
        if (!$project) return $this->error('Project not found', 404);

        $newProject = $project->replicate();
        $newProject->project_name = $project->project_name . ' (Duplicate)';
        $newProject->created_by = Auth::user()->creatorId();
        $newProject->save();

        // Duplicate tasks, milestones, etc. logic could go here

        return $this->success(['project' => $newProject], 'Project duplicated successfully.');
    }

    public function getProjectTasks($id)
    {
        $project = Project::find($id);
        if (!$project) return $this->error('Project not found.', 404);

        $tasks = ProjectTask::where('project_id', $id)->get();
        return $this->success(['tasks' => $tasks], 'Project tasks retrieved.');
    }

    public function getProjectMilestones($id)
    {
        $milestones = Milestone::where('project_id', $id)->get();
        return $this->success(['milestones' => $milestones], 'Project milestones retrieved.');
    }

    public function getProjectBugs($id)
    {
        $bugs = Bug::where('project_id', $id)->get();
        return $this->success(['bugs' => $bugs], 'Project bugs retrieved.');
    }

    public function getProjectFiles($id)
    {
        $project = Project::find($id);
        if (!$project) return $this->error('Project not found.', 404);

        $files = $project->projectAttachments();
        $files->each(function ($file) {
            if (!empty($file->file)) {
                $file->file_url = url('api/v1/files/projects/' . $file->id);
            } else {
                $file->file_url = null;
            }
        });

        return $this->success(['files' => $files], 'Project files retrieved.');
    }

    /**
     * Project files are the attachments of the project's tasks (Project::projectAttachments),
     * so an upload has to name the task it belongs to.
     */
    public function uploadProjectFile(Request $request, $id)
    {
        $project = Project::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$project) return $this->error('Project not found.', 404);

        $validator = Validator::make($request->all(), [
            'task_id' => 'required|integer',
            'file' => 'required|file',
        ]);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        if (!ProjectTask::where('id', $request->task_id)->where('project_id', $project->id)->exists()) {
            return $this->error('The selected task does not belong to this project.', 422);
        }

        return app(TaskApiController::class)->uploadTaskFile($request, $request->task_id);
    }

    public function getProjectMembers($id)
    {
        $project = Project::with('users')->find($id);
        if (!$project) return $this->error('Project not found.', 404);

        return $this->success(['members' => $project->users], 'Project members retrieved.');
    }

    public function addProjectMember(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $project = Project::find($id);
        if (!$project) return $this->error('Project not found.', 404);

        if (ProjectUser::where('project_id', $project->id)->where('user_id', $request->user_id)->exists()) {
            return $this->error('User already exists in the project.', 400);
        }

        ProjectUser::create([
            'project_id' => $project->id,
            'user_id' => $request->user_id,
            'invited_by' => Auth::user()->id,
        ]);

        return $this->success([], 'User added to project successfully.');
    }

    public function removeProjectMember($id, $user_id)
    {
        $project = Project::find($id);
        if (!$project) return $this->error('Project not found.', 404);

        ProjectUser::where('project_id', $project->id)->where('user_id', $user_id)->delete();
        return $this->success([], 'User removed from project successfully.');
    }

    public function getProjectActivity($id)
    {
        $project = Project::find($id);
        if (!$project) return $this->error('Project not found.', 404);

        $activities = ActivityLog::where('project_id', $id)->get();
        return $this->success(['activity' => $activities], 'Project activity retrieved.');
    }

    public function getProjectTimesheet($id)
    {
        $project = Project::find($id);
        if (!$project) return $this->error('Project not found.', 404);

        $timesheets = $project->timesheets;
        return $this->success(['timesheet' => $timesheets], 'Project timesheet retrieved.');
    }

    public function getProjectExpenses($id)
    {
        $project = Project::find($id);
        if (!$project) return $this->error('Project not found.', 404);

        $expenses = Expense::where('project_id', $id)->get();
        return $this->success(['expenses' => $expenses], 'Project expenses retrieved.');
    }

    public function createProjectExpense(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required',
            'date' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $expense = new Expense();
        $expense->project_id = $id;
        $expense->amount = $request->amount;
        $expense->date = $request->date;
        $expense->description = $request->description;
        $expense->attachment = $request->attachment;
        $expense->created_by = Auth::user()->creatorId();
        $expense->save();

        return $this->success(['expense' => $expense], 'Expense created successfully.');
    }

    public function getDepartmentProjects(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'department_id' => 'required',
            'view' => 'nullable|string|in:grid,list',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $departmentId = $request->department_id;
        $view = $request->view ?: 'grid';
        $user = Auth::user();

        // Check if department exists and belongs to user's company
        $department = Department::where('id', $departmentId)
            ->where('created_by', $user->creatorId())
            ->first();

        if (!$department) {
            return $this->error('Department not found or access denied.', 404);
        }

        // Get projects for this department based on user permissions
        // Company owners and super admins can see all projects
        // Regular users can only see projects they are assigned to
        if ($user->type == 'company' || $user->type == 'super admin') {
            $projects = Project::where('department_id', $department->id)
                ->where('created_by', $user->creatorId())
                ->with(['users', 'client'])
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            // Filter projects where the user is a member
            $projects = Project::where('department_id', $department->id)
                ->where('created_by', $user->creatorId())
                ->whereHas('users', function ($query) use ($user) {
                    $query->where('users.id', $user->id);
                })
                ->with(['users', 'client'])
                ->orderBy('created_at', 'desc')
                ->get();
        }

        // Get project statistics for the department
        $totalProjects = $projects->count();
        $activeProjects = $projects->where('status', 'active')->count();
        $completedProjects = $projects->where('status', 'completed')->count();
        $onHoldProjects = $projects->where('status', 'on_hold')->count();

        return $this->success([
            'projects' => $projects,
            'department' => $department,
            'view' => $view,
            'department_id' => $department->id,
            'department_name' => $department->name,
            'statistics' => [
                'total_projects' => $totalProjects,
                'active_projects' => $activeProjects,
                'completed_projects' => $completedProjects,
                'on_hold_projects' => $onHoldProjects,
            ]
        ], 'Department projects retrieved.');
    }

    public function serveProjectFile($fileId)
    {
        $file = \App\Models\TaskFile::find($fileId);
        if (!$file) {
            return response()->json([
                'is_success' => false,
                'message' => 'File record not found in database.',
                'error_code' => 'FILE_RECORD_NOT_FOUND'
            ], 404);
        }

        $filePath = storage_path('uploads/tasks/' . $file->file);

        if (!file_exists($filePath)) {
            // Log the missing file
            \Log::warning('Project file missing from server', [
                'file_id' => $fileId,
                'file_name' => $file->file,
                'expected_path' => $filePath,
                'task_id' => $file->task_id
            ]);

            return response()->json([
                'is_success' => false,
                'message' => 'File not found on server. The file may have been deleted or moved.',
                'error_code' => 'FILE_NOT_FOUND_ON_SERVER',
                'data' => [
                    'file_id' => $fileId,
                    'file_name' => $file->name . '.' . $file->extension,
                    'task_id' => $file->task_id
                ]
            ], 404);
        }

        // Determine MIME type
        $mimeType = mime_content_type($filePath);

        return response()->file($filePath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $file->name . '.' . $file->extension . '"',
        ]);
    }
}
