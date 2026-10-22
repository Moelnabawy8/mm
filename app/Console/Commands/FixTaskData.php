<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProjectTask;
use App\Models\Project;
use App\Models\User;

class FixTaskData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:task-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix inconsistent task data (orphaned tasks, invalid users/priorities)';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting task data cleanup...');

        // 1. Fix Orphaned Tasks (Missing Project)
        $this->info('Checking for orphaned tasks...');
        $tasks = ProjectTask::all();
        $orphanedCount = 0;
        foreach ($tasks as $task) {
            if (!Project::find($task->project_id)) {
                $this->warn("Deleting orphaned task ID {$task->id} (Project {$task->project_id} missing)");
                $task->delete();
                $orphanedCount++;
            }
        }
        $this->info("Deleted {$orphanedCount} orphaned tasks.");

        // 2. Fix Invalid Assignees
        $this->info('Checking for invalid assignees...');
        $tasks = ProjectTask::all(); 
        $allUserIds = User::pluck('id')->toArray();
        $fixedAssigneesCount = 0;

        foreach ($tasks as $task) {
             if (!empty($task->assign_to)) {
                 $assignees = explode(',', $task->assign_to);
                 $validAssignees = array_intersect($assignees, $allUserIds);
                 
                 // If counts differ, we have invalid users
                 if (count($assignees) != count($validAssignees)) {
                     $newTaskAssignees = implode(',', $validAssignees);
                     $task->assign_to = $newTaskAssignees;
                     $task->save();
                     $fixedAssigneesCount++;
                 }
             }
        }
        $this->info("Fixed assignees for {$fixedAssigneesCount} tasks.");
        
        // 3. Fix Invalid Priorities
        $this->info('Checking for invalid priorities...');
        $tasks = ProjectTask::all();
        $validPriorities = array_keys(ProjectTask::$priority);
        $fixedPriorityCount = 0;

        foreach ($tasks as $task) {
            if (!in_array($task->priority, $validPriorities)) {
                $this->warn("Fixing invalid priority '{$task->priority}' for task ID {$task->id}");
                $task->priority = 'medium'; // Default to medium
                $task->save();
                $fixedPriorityCount++;
            }
        }
        $this->info("Fixed priorities for {$fixedPriorityCount} tasks.");

        $this->info('Task data cleanup completed successfully.');
        return 0;
    }
}
