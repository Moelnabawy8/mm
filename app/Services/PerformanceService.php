<?php

namespace App\Services;

use App\Models\EmployeePerformance;
use App\Models\Employee;
use App\Models\TaskLog;
use App\Models\ProjectTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PerformanceService — computes daily and on-demand performance scores.
 *
 * Score formula (locked in by user):
 *   productivity     = active_time / total_time
 *   task_completion  = completed_tasks / total_tasks
 *   idle_ratio       = idle_time / total_time
 *   on_time          = completed_on_time / completed_tasks
 *
 *   score = 0.4*productivity + 0.3*task_completion
 *         + 0.2*(1-idle_ratio) + 0.1*on_time
 *   stars = round(score * 5)
 */
class PerformanceService
{
    public const W_PRODUCTIVITY    = 0.4;
    public const W_TASK_COMPLETION = 0.3;
    public const W_NOT_IDLE        = 0.2;
    public const W_ON_TIME         = 0.1;

    /**
     * Compute & upsert performance for ONE user on ONE date.
     */
    public function computeForUser(int $userId, Carbon $date): EmployeePerformance
    {
        $start = $date->copy()->startOfDay();
        $end   = $date->copy()->endOfDay();

        // 1. Time totals from task_logs
        $logs = TaskLog::where('user_id', $userId)
            ->whereBetween('start_time', [$start, $end])
            ->get();

        $activeSec = (int) $logs->sum('active_seconds');
        $idleSec   = (int) $logs->sum('idle_seconds');
        $totalSec  = max(1, $activeSec + $idleSec); // avoid div by 0

        // 2. Task counts: tasks ASSIGNED to this user with end_date <= today
        //    (i.e. tasks "due" by now — completed-or-not denominator).
        //    We look at project_tasks via the existing assign_to FK.
        $employee = Employee::where('user_id', $userId)->first();
        $assigneeId = $employee ? $employee->user_id : $userId;

        $tasks = ProjectTask::where('assign_to', $assigneeId)
            ->whereDate('end_date', '<=', $end->toDateString())
            ->get(['id', 'is_complete', 'end_date', 'marked_at']);

        $totalTasks       = $tasks->count();
        $completedTasks   = $tasks->where('is_complete', 1)->count();
        $completedOnTime  = $tasks->where('is_complete', 1)->filter(function ($t) {
            // on time = marked_at <= end_date (or end_date is null)
            if (!$t->marked_at || !$t->end_date) return true;
            return Carbon::parse($t->marked_at)->lte(Carbon::parse($t->end_date)->endOfDay());
        })->count();

        // 3. Derived ratios (0..1)
        $productivity   = $activeSec / $totalSec;
        $idleRatio      = $idleSec / $totalSec;
        $taskCompletion = $totalTasks > 0 ? $completedTasks / $totalTasks : 0;
        $onTime         = $completedTasks > 0 ? $completedOnTime / $completedTasks : 0;

        // 4. Composite score & stars
        $score = self::W_PRODUCTIVITY    * $productivity
               + self::W_TASK_COMPLETION * $taskCompletion
               + self::W_NOT_IDLE        * (1 - $idleRatio)
               + self::W_ON_TIME         * $onTime;
        $score = max(0, min(1, $score));
        $stars = (int) round($score * 5);

        // 5. Upsert daily snapshot (idempotent: re-running overwrites)
        return EmployeePerformance::updateOrCreate(
            ['user_id' => $userId, 'date' => $date->toDateString()],
            [
                'active_seconds'    => $activeSec,
                'idle_seconds'      => $idleSec,
                'total_tasks'       => $totalTasks,
                'completed_tasks'   => $completedTasks,
                'completed_on_time' => $completedOnTime,
                'productivity'      => round($productivity, 4),
                'task_completion'   => round($taskCompletion, 4),
                'idle_ratio'        => round($idleRatio, 4),
                'on_time'           => round($onTime, 4),
                'score'             => round($score, 4),
                'stars'             => $stars,
                'computed_at'       => now(),
            ]
        );
    }

    /**
     * Aggregate across a date range — used for the employee profile widget
     * and the admin dashboard "top performers" list.
     * Returns ['stars' => int, 'score' => float, 'days' => int].
     */
    public function summary(int $userId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?: Carbon::now()->subDays(29)->startOfDay();
        $to   = $to   ?: Carbon::now()->endOfDay();

        $rows = EmployeePerformance::forUser($userId)->between($from, $to)->get();
        if ($rows->isEmpty()) {
            return ['stars' => 0, 'score' => 0.0, 'days' => 0, 'productivity' => 0, 'idle_ratio' => 0];
        }

        $avgScore = $rows->avg('score');
        return [
            'stars'        => (int) round($avgScore * 5),
            'score'        => round($avgScore, 4),
            'days'         => $rows->count(),
            'productivity' => round($rows->avg('productivity'), 4),
            'idle_ratio'   => round($rows->avg('idle_ratio'), 4),
        ];
    }

    /**
     * Used by scheduler: compute YESTERDAY's performance for every user
     * who had a task_log activity that day.
     */
    public function computeYesterdayForAll(): int
    {
        $date = Carbon::yesterday();
        $userIds = TaskLog::whereDate('start_time', $date->toDateString())
            ->distinct('user_id')->pluck('user_id');
        foreach ($userIds as $uid) {
            $this->computeForUser((int) $uid, $date);
        }
        return $userIds->count();
    }
}
