<?php

use App\Models\TaskStage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repair task completion, which had never worked correctly.
 *
 * task_stages.complete was created with a default of 0 and no code path ever
 * set it, so the board fell back to "whichever stage sorts last" to decide
 * whether a task was finished. Adding "On Hold" (order 5) after "Done"
 * (order 3) made On Hold the completion stage: every card dragged into Done
 * had is_complete cleared and marked_at wiped, while the two cards parked in
 * On Hold were flagged as finished.
 *
 * This flags the real completion stage and re-derives is_complete from where
 * each task actually sits.
 *
 * marked_at is deliberately NOT backfilled. The original completion dates
 * were destroyed and updated_at is not a stand-in - it moves on any edit, so
 * a task closed in 2024 and edited last week would report a completion time
 * of over a year and skew "Avg. Days to Complete" further than leaving the
 * value empty does. New completions record the date correctly from now on.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->companyIds() as $createdBy) {
            // completedIds() falls back to name matching when nothing is
            // flagged yet, which is exactly the set we want to promote.
            $completed = TaskStage::completedIds($createdBy);

            if (empty($completed)) {
                continue;
            }

            TaskStage::whereIn('id', $completed)->update(['complete' => 1]);
            TaskStage::where('created_by', $createdBy)
                ->whereNotIn('id', $completed)
                ->update(['complete' => 0]);

            // Re-derive the flag from the stage each task is parked in.
            DB::table('project_tasks')
                ->where('created_by', $createdBy)
                ->whereIn('stage_id', $completed)
                ->where('is_complete', '!=', 1)
                ->update(['is_complete' => 1]);

            DB::table('project_tasks')
                ->where('created_by', $createdBy)
                ->whereNotIn('stage_id', $completed)
                ->where('is_complete', 1)
                ->update(['is_complete' => 0, 'marked_at' => null]);
        }
    }

    /**
     * Every company that owns task stages. Tasks and stages are both scoped by
     * created_by, so the repair runs per company rather than globally.
     */
    private function companyIds(): array
    {
        return TaskStage::select('created_by')->distinct()->pluck('created_by')->filter()->toArray();
    }

    public function down(): void
    {
        // is_complete cannot be put back: the values it held were wrong, and
        // the correct ones were never recorded. Only the flag is reversible.
        TaskStage::query()->update(['complete' => 0]);
    }
};
