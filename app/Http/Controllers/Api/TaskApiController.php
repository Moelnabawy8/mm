<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProjectTask;
use App\Models\Project;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Utility;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TaskApiController extends Controller
{
    use ApiResponser;

    public function getTasks(Request $request)
    {
        $perPage = (int) ($request->per_page ?? 15);
        $user = Auth::user();
        $query = ProjectTask::where('created_by', $user->creatorId());

        // If employee, get only their assigned tasks
        if ($user->type == 'employee') {
            $employee = \App\Models\Employee::where('user_id', $user->id)->first();
            if (!$employee) {
                return $this->error('Employee record not found. Please contact administrator to create your employee profile.', 404);
            }

            $query->where(function ($q) use ($user) {
                $q->where('assign_to', 'LIKE', '%' . $user->id . '%')
                  ->orWhere('assign_to', $user->id);
            });
        }

        // Search by task name
        if ($request->search) {
            $s = $request->search;
            $query->where('name', 'like', "%{$s}%");
        }

        // Filter by status (stage)
        if ($request->status) {
            $query->where('stage_id', $request->status);
        }

        $result = $query->with(['project', 'stage', 'taskFiles'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Build summary by stage (fresh clone with same filters)
        $stageQuery = ProjectTask::where('created_by', $user->creatorId());
        if ($user->type == 'employee') {
            $stageQuery->where(function ($q) use ($user) {
                $q->where('assign_to', 'LIKE', '%' . $user->id . '%')
                  ->orWhere('assign_to', $user->id);
            });
        }
        if ($request->search) {
            $s = $request->search;
            $stageQuery->where('name', 'like', "%{$s}%");
        }
        if ($request->status) {
            $stageQuery->where('stage_id', $request->status);
        }

        $byStage = $stageQuery->select('stage_id', \Illuminate\Support\Facades\DB::raw('COUNT(*) as count'))
            ->with('stage:id,name')
            ->groupBy('stage_id')
            ->get()
            ->map(function ($item) {
                return [
                    'stage_id' => $item->stage_id,
                    'stage_name' => $item->stage?->name ?? 'Unknown',
                    'count' => $item->count,
                ];
            })
            ->toArray();

        // Build summary by priority (fresh clone with same filters)
        $priorityQuery = ProjectTask::where('created_by', $user->creatorId());
        if ($user->type == 'employee') {
            $priorityQuery->where(function ($q) use ($user) {
                $q->where('assign_to', 'LIKE', '%' . $user->id . '%')
                  ->orWhere('assign_to', $user->id);
            });
        }
        if ($request->search) {
            $s = $request->search;
            $priorityQuery->where('name', 'like', "%{$s}%");
        }
        if ($request->status) {
            $priorityQuery->where('stage_id', $request->status);
        }

        $byPriority = $priorityQuery->select('priority', \Illuminate\Support\Facades\DB::raw('COUNT(*) as count'))
            ->groupBy('priority')
            ->get()
            ->map(function ($item) {
                return [
                    'priority' => $item->priority,
                    'count' => $item->count,
                ];
            })
            ->toArray();

        return $this->success([
            'tasks'      => $result->items(),
            'summary'    => [
                'total_tasks'  => $result->total(),
                'by_stage'     => $byStage,
                'by_priority'  => $byPriority,
            ],
            'pagination' => [
                'total'        => $result->total(),
                'per_page'     => $result->perPage(),
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
            ],
        ], 'Tasks retrieved.');
    }

    public function getMyTasks(Request $request)
    {
        $perPage = (int) ($request->per_page ?? 15);
        $user = Auth::user();
        $employee = \App\Models\Employee::where('user_id', $user->id)->first();

        if (!$employee) {
            return $this->error('Employee record not found. Please contact administrator to create your employee profile.', 404);
        }

        $query = ProjectTask::where('created_by', $user->creatorId())
            ->where(function ($q) use ($user) {
                $q->where('assign_to', 'LIKE', '%' . $user->id . '%')
                  ->orWhere('assign_to', $user->id);
            });

        // Search by task name
        if ($request->search) {
            $s = $request->search;
            $query->where('name', 'like', "%{$s}%");
        }

        // Filter by status (stage)
        if ($request->status) {
            $query->where('stage_id', $request->status);
        }

        $result = $query->with(['project', 'stage', 'taskFiles'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Build summary by stage (always filter by user assignment)
        $stageQuery = ProjectTask::where('created_by', $user->creatorId())
            ->where(function ($q) use ($user) {
                $q->where('assign_to', 'LIKE', '%' . $user->id . '%')
                  ->orWhere('assign_to', $user->id);
            });
        if ($request->search) {
            $s = $request->search;
            $stageQuery->where('name', 'like', "%{$s}%");
        }
        if ($request->status) {
            $stageQuery->where('stage_id', $request->status);
        }

        $byStage = $stageQuery->select('stage_id', \Illuminate\Support\Facades\DB::raw('COUNT(*) as count'))
            ->with('stage:id,name')
            ->groupBy('stage_id')
            ->get()
            ->map(function ($item) {
                return [
                    'stage_id' => $item->stage_id,
                    'stage_name' => $item->stage?->name ?? 'Unknown',
                    'count' => $item->count,
                ];
            })
            ->toArray();

        // Build summary by priority (always filter by user assignment)
        $priorityQuery = ProjectTask::where('created_by', $user->creatorId())
            ->where(function ($q) use ($user) {
                $q->where('assign_to', 'LIKE', '%' . $user->id . '%')
                  ->orWhere('assign_to', $user->id);
            });
        if ($request->search) {
            $s = $request->search;
            $priorityQuery->where('name', 'like', "%{$s}%");
        }
        if ($request->status) {
            $priorityQuery->where('stage_id', $request->status);
        }

        $byPriority = $priorityQuery->select('priority', \Illuminate\Support\Facades\DB::raw('COUNT(*) as count'))
            ->groupBy('priority')
            ->get()
            ->map(function ($item) {
                return [
                    'priority' => $item->priority,
                    'count' => $item->count,
                ];
            })
            ->toArray();

        return $this->success([
            'tasks'      => $result->items(),
            'summary'    => [
                'total_tasks'  => $result->total(),
                'by_stage'     => $byStage,
                'by_priority'  => $byPriority,
            ],
            'pagination' => [
                'total'        => $result->total(),
                'per_page'     => $result->perPage(),
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
            ],
        ], 'My tasks retrieved.');
    }

    public function createProjectTask(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|integer|exists:projects,id',
            'name' => 'required',
            'estimated_hrs' => 'required',
            'priority' => 'required',
            'stage_id' => 'required',
            'start_date' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 401);
        }

        $usr = Auth::user();
        $project = Project::find($request->project_id);
        $post = $request->all();
        $post['created_by'] = $usr->creatorId();
        $post['start_date'] = date("Y-m-d H:i:s", strtotime($request->start_date));
        $post['end_date'] = isset($request->end_date) ? date("Y-m-d H:i:s", strtotime($request->end_date)) : null;

        $task = ProjectTask::create($post);

        return $this->success(['task' => $task], 'Task added successfully.');
    }

    public function getProjectTask($id)
    {
        $task = ProjectTask::where('id', $id)
            ->with(['project', 'stage', 'checklist', 'taskFiles', 'comments.user'])
            ->first();

        if (!$task) return $this->error('Task not found', 404);

        return $this->success(['task' => $task], 'Task retrieved.');
    }

    public function getTasksByProject($project_id)
    {
        $project = \App\Models\Project::find($project_id);
        if (!$project) return $this->error('Project not found.', 404);

        $tasks = ProjectTask::where('project_id', $project_id)
            ->with(['stage', 'checklist'])
            ->orderBy('order')
            ->get();

        return $this->success(['tasks' => $tasks], 'Project tasks retrieved.');
    }

    public function getTasksByUser(Request $request, $user_id)
    {
        $authUser = Auth::user();

        $user = \App\Models\User::where('id', $user_id)
            ->where(function ($q) use ($authUser) {
                $q->where('id', $authUser->creatorId())
                  ->orWhere('created_by', $authUser->creatorId());
            })
            ->first();

        if (!$user) return $this->error('User not found.', 404);

        $userId  = $user->id;
        $perPage = (int) ($request->per_page ?? 15);

        $buildQuery = function () use ($authUser, $userId) {
            return ProjectTask::where('created_by', $authUser->creatorId())
                ->where(function ($q) use ($userId) {
                    $q->where('assign_to', 'LIKE', '%' . $userId . '%')
                      ->orWhere('assign_to', $userId);
                });
        };

        $query = $buildQuery();

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
        if ($request->status) {
            $query->where('stage_id', $request->status);
        }

        $result = $query->with(['project', 'stage', 'taskFiles'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $stageQuery = $buildQuery();
        if ($request->search) $stageQuery->where('name', 'like', '%' . $request->search . '%');
        if ($request->status) $stageQuery->where('stage_id', $request->status);

        $byStage = $stageQuery->select('stage_id', \Illuminate\Support\Facades\DB::raw('COUNT(*) as count'))
            ->with('stage:id,name')
            ->groupBy('stage_id')
            ->get()
            ->map(fn($i) => ['stage_id' => $i->stage_id, 'stage_name' => $i->stage?->name ?? 'Unknown', 'count' => $i->count])
            ->toArray();

        $priorityQuery = $buildQuery();
        if ($request->search) $priorityQuery->where('name', 'like', '%' . $request->search . '%');
        if ($request->status) $priorityQuery->where('stage_id', $request->status);

        $byPriority = $priorityQuery->select('priority', \Illuminate\Support\Facades\DB::raw('COUNT(*) as count'))
            ->groupBy('priority')
            ->get()
            ->map(fn($i) => ['priority' => $i->priority, 'count' => $i->count])
            ->toArray();

        return $this->success([
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
            'tasks'      => $result->items(),
            'summary'    => [
                'total_tasks' => $result->total(),
                'by_stage'    => $byStage,
                'by_priority' => $byPriority,
            ],
            'pagination' => [
                'total'        => $result->total(),
                'per_page'     => $result->perPage(),
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
            ],
        ], 'User tasks retrieved.');
    }

    public function updateProjectTask(Request $request, $id)
    {
        $task = ProjectTask::find($id);
        if (!$task) return $this->error('Task not found', 404);
        $task->update($request->all());
        return $this->success(['task' => $task], 'Task updated.');
    }

    public function deleteProjectTask($id)
    {
        $task = ProjectTask::find($id);
        if (!$task) return $this->error('Task not found', 404);
        $task->delete();
        return $this->success([], 'Task deleted.');
    }

    public function completeTask($id)
    {
        $task = ProjectTask::find($id);
        if (!$task) return $this->error('Task not found.', 404);

        $task->is_complete = 1;
        $task->marked_at = date('Y-m-d');
        $task->save();

        return $this->success(['task' => $task], 'Task completed successfully.');
    }

    public function addTaskComment(Request $request, $id)
    {
        $validator = Validator::make($request->all(), ['comment' => 'required']);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 401);

        $task = ProjectTask::find($id);
        if (!$task) return $this->error('Task not found.', 404);

        $comment = new \App\Models\TaskComment();
        $comment->comment = $request->comment;
        $comment->task_id = $task->id;
        $comment->user_id = Auth::user()->id;
        $comment->user_type = Auth::user()->type;
        $comment->created_by = Auth::user()->creatorId();
        $comment->save();

        return $this->success(['comment' => $comment], 'Comment added successfully.');
    }

    public function getTaskComments($id)
    {
        $comments = \App\Models\TaskComment::with('user')->where('task_id', $id)->get();
        return $this->success(['comments' => $comments], 'Task comments retrieved.');
    }

    public function uploadTaskFile(Request $request, $id)
    {
        $validator = Validator::make($request->all(), ['file' => 'required|file']);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 401);

        $task = ProjectTask::find($id);
        if (!$task) return $this->error('Task not found.', 404);

        $file = $request->file('file');
        $filenameWithExt = $file->getClientOriginalName();
        $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();
        $fileNameToStore = $filename . '_' . time() . '.' . $extension;
        $dir = storage_path('uploads/tasks');
        if (!file_exists($dir)) mkdir($dir, 0777, true);
        $file->storeAs('uploads/tasks', $fileNameToStore);

        $taskFile = new \App\Models\TaskFile();
        $taskFile->task_id = $task->id;
        $taskFile->file = $fileNameToStore;
        $taskFile->name = $filename;
        $taskFile->extension = $extension;
        $taskFile->file_size = round($file->getSize() / 1024 / 1024, 2) . ' MB';
        $taskFile->user_type = Auth::user()->type;
        $taskFile->created_by = Auth::user()->creatorId();
        $taskFile->save();

        return $this->success(['file' => $taskFile], 'File uploaded successfully.');
    }

    public function getTaskFiles($id)
    {
        $files = \App\Models\TaskFile::where('task_id', $id)->get();
        $files->each(function ($file) {
            if (!empty($file->file)) {
                $file->file_url = url('api/v1/files/tasks/' . $file->id);
            } else {
                $file->file_url = null;
            }
        });
        return $this->success(['files' => $files], 'Task files retrieved.');
    }

    public function addTaskChecklist(Request $request, $id)
    {
        $validator = Validator::make($request->all(), ['name' => 'required']);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 401);

        $checklist = new \App\Models\TaskChecklist();
        $checklist->name = $request->name;
        $checklist->task_id = $id;
        $checklist->user_type = Auth::user()->type;
        $checklist->created_by = Auth::user()->creatorId();
        $checklist->status = 0;
        $checklist->save();

        return $this->success(['checklist' => $checklist], 'Checklist added successfully.');
    }

    public function getTaskTimeTracker($id)
    {
        $trackers = \App\Models\TimeTracker::where('task_id', $id)->get();
        return $this->success(['trackers' => $trackers], 'Time trackers retrieved.');
    }

    /**
     * Also serves POST /tasks/{id}/check-in. Same rules as /time-tracker/start and the
     * web tracker: one running timer per user at a time, whatever task it is on.
     */
    public function startTimeTracker($id)
    {
        $task = $this->companyTask($id);
        if (!$task) return $this->error('Task not found.', 404);

        $active = \App\Models\TimeTracker::where('user_id', Auth::user()->id)
            ->where('created_by', Auth::user()->creatorId())
            ->whereNull('end_time')
            ->first();
        if ($active) {
            $message = $active->task_id == $task->id
                ? 'You are already checked in to this task.'
                : 'You are checked in to another task. Check out of it first.';
            return $this->error($message, 409, ['active_tracker' => $active]);
        }

        $tracker = new \App\Models\TimeTracker();
        $tracker->project_id = $task->project_id;
        $tracker->task_id = $task->id;
        $tracker->name = $task->name;
        $tracker->user_id = Auth::user()->id;
        $tracker->created_by = Auth::user()->creatorId();
        $tracker->start_time = date('Y-m-d H:i:s');
        $tracker->is_active = 1;
        $tracker->save();

        return $this->success(['tracker' => $tracker], 'Time tracking started.');
    }

    /** Also serves POST /tasks/{id}/check-out. */
    public function stopTimeTracker($id)
    {
        $task = $this->companyTask($id);
        if (!$task) return $this->error('Task not found.', 404);

        $tracker = \App\Models\TimeTracker::where('task_id', $task->id)
            ->where('user_id', Auth::user()->id)
            ->whereNull('end_time')
            ->first();

        if (!$tracker) return $this->error('No active tracker found for this task.', 404);

        $tracker->end_time = date('Y-m-d H:i:s');
        $tracker->total_time = \App\Models\Utility::diffance_to_time($tracker->start_time, $tracker->end_time);
        $tracker->is_active = 0; // the web tracker reads is_active, not end_time
        $tracker->save();

        return $this->success(['tracker' => $tracker], 'Time tracking stopped.');
    }

    private function companyTask($id)
    {
        $task = ProjectTask::find($id);
        if (!$task || !Project::where('id', $task->project_id)->where('created_by', Auth::user()->creatorId())->exists()) {
            return null;
        }

        return $task;
    }

    public function updateTaskStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string',
        ]);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        $task = ProjectTask::find($id);
        if (!$task) return $this->error('Task not found.', 404);

        $user = Auth::user();

        // Find matching stage by name (case-insensitive)
        $stage = \App\Models\TaskStage::where('name', 'like', $request->status)
            ->where('created_by', $user->creatorId())
            ->first();

        if (!$stage) {
            $availableStages = \App\Models\TaskStage::where('created_by', $user->creatorId())
                ->pluck('name')->toArray();
            return $this->error('Invalid status. Available: ' . implode(', ', $availableStages), 422);
        }

        $task->stage_id = $stage->id;

        // Auto-mark complete if it's the last stage
        $lastStage = \App\Models\TaskStage::where('created_by', $user->creatorId())
            ->orderBy('order', 'desc')->first();

        if ($lastStage && $stage->id === $lastStage->id) {
            $task->is_complete = 1;
            $task->marked_at = date('Y-m-d');
        } else {
            $task->is_complete = 0;
            $task->marked_at = null;
        }

        $task->save();

        return $this->success(['task' => $task->load('stage')], 'Task status updated successfully.');
    }

    public function uploadFile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file'          => 'required|file|max:20480',
            'resource_type' => 'required|in:employee,project,task',
            'resource_id'   => 'required|integer',
        ]);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        $user = Auth::user();
        $resourceType = $request->resource_type;
        $resourceId = $request->resource_id;

        // Validate that the resource exists
        switch ($resourceType) {
            case 'task':
                $resource = ProjectTask::find($resourceId);
                if (!$resource) return $this->error('Task not found.', 404);
                $storageDir = 'uploads/tasks';
                break;

            case 'project':
                $resource = Project::where('id', $resourceId)
                    ->where('created_by', $user->creatorId())->first();
                if (!$resource) return $this->error('Project not found.', 404);
                $storageDir = 'uploads/projects';
                break;

            case 'employee':
                $resource = \App\Models\Employee::where('id', $resourceId)
                    ->where('created_by', $user->creatorId())->first();
                if (!$resource) return $this->error('Employee not found.', 404);
                $storageDir = 'uploads/document';
                break;
        }

        // Process file upload
        $file = $request->file('file');
        $filenameWithExt = $file->getClientOriginalName();
        $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();
        $fileNameToStore = $filename . '_' . time() . '.' . $extension;
        $dir = storage_path($storageDir);
        if (!file_exists($dir)) mkdir($dir, 0777, true);
        $file->storeAs($storageDir, $fileNameToStore);

        $fileSize = round($file->getSize() / 1024 / 1024, 2) . ' MB';
        $fileUrl = asset('storage/' . $storageDir . '/' . $fileNameToStore);

        // Create appropriate model record
        $record = null;
        switch ($resourceType) {
            case 'task':
                $record = new \App\Models\TaskFile();
                $record->task_id = $resourceId;
                $record->file = $fileNameToStore;
                $record->name = $filename;
                $record->extension = $extension;
                $record->file_size = $fileSize;
                $record->user_type = $user->type;
                $record->created_by = $user->creatorId();
                $record->save();
                break;

            case 'project':
                // Projects use TaskFiles via project tasks — store as a project-level attachment
                // Create a TaskFile linked to the project's first task, or store standalone
                $firstTask = ProjectTask::where('project_id', $resourceId)->first();
                if ($firstTask) {
                    $record = new \App\Models\TaskFile();
                    $record->task_id = $firstTask->id;
                    $record->file = $fileNameToStore;
                    $record->name = $filename;
                    $record->extension = $extension;
                    $record->file_size = $fileSize;
                    $record->user_type = $user->type;
                    $record->created_by = $user->creatorId();
                    $record->save();
                } else {
                    $record = (object)[
                        'file' => $fileNameToStore,
                        'name' => $filename,
                        'extension' => $extension,
                        'file_size' => $fileSize,
                    ];
                }
                break;

            case 'employee':
                $record = new \App\Models\EmployeeDocument();
                $record->employee_id = $resourceId;
                $record->document_id = $request->document_id ?? 0;
                $record->document_value = $fileNameToStore;
                $record->created_by = $user->creatorId();
                $record->save();
                break;
        }

        return $this->success([
            'file' => $record,
            'file_url' => $fileUrl,
            'metadata' => [
                'original_name' => $filenameWithExt,
                'name' => $filename,
                'extension' => $extension,
                'size' => $fileSize,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
            ]
        ], 'File uploaded successfully.');
    }

    public function serveTaskFile($fileId)
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
            \Log::warning('Task file missing from server', [
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
