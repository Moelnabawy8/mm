<?php

namespace App\Services\Quality;

use App\Models\Department;
use App\Models\ProjectTask;
use App\Models\QualityCriterion;
use App\Models\QualityDepartmentReviewer;
use App\Models\TaskQualityReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Every number the quality screens show comes from here.
 *
 * Written as one service rather than queries spread across the controller and the
 * blades because "what counts as awaiting review" is a definition, not a query: it is
 * a completed task, in a project filed under this department, with no review row. Three
 * screens need that same answer and they must not drift apart.
 *
 * Three facts about this install shape the whole thing:
 *
 *   - A task reaches a department through its project (projects.department_id), and
 *     that path holds: 2,978 of 3,047 tasks resolve. There is no department_id on
 *     project_tasks and none is added here.
 *   - Sales has 26 staff and no completed tasks — its work is leads, judged by the
 *     separate lead-evaluation module. Departments with no task flow are shown as
 *     inactive rather than as empty scoreboards, because a 0% next to a department
 *     that never files tasks reads as a failing grade rather than as "not applicable".
 *   - A completed task counts only if it carries a completion date. marked_at is the
 *     one field that means "finished on"; updated_at moves on any edit, and a
 *     historical ProjectTaskController bug wiped marked_at on cards dragged into
 *     Done, so thousands of old tasks have no date and a recent updated_at. Treating
 *     those as recent work put 1,444 rows in a queue whose honest size was ~200.
 */
class QualityBoard
{
    /** Completed tasks older than this are not chased for review. */
    private const REVIEW_WINDOW_DAYS = 90;

    /**
     * One row per department: how much work it completed, how much was reviewed, and
     * how it scored. Ordered so the departments that actually produce come first.
     */
    public function departments(int $createdBy): array
    {
        $reviewStats = TaskQualityReview::where('created_by', $createdBy)
            ->select(
                'department_id',
                DB::raw('COUNT(*) as reviews'),
                DB::raw('AVG(total_score) as avg_score'),
                DB::raw('MAX(reviewed_at) as last_reviewed_at')
            )
            ->groupBy('department_id')
            ->get()
            ->keyBy('department_id');

        $completed = $this->completedTaskCountsByDepartment();
        $pending   = $this->pendingTaskCountsByDepartment();

        $criteria = QualityCriterion::active()
            ->select('department_id', DB::raw('COUNT(*) as n'))
            ->groupBy('department_id')
            ->pluck('n', 'department_id');

        $reviewers = QualityDepartmentReviewer::select('department_id', DB::raw('COUNT(*) as n'))
            ->groupBy('department_id')
            ->pluck('n', 'department_id');

        $rows = [];

        foreach (Department::orderBy('name')->get() as $dept) {
            $stat = $reviewStats->get($dept->id);
            $done = (int) ($completed[$dept->id] ?? 0);

            $rows[] = [
                'id'             => $dept->id,
                'name'           => $dept->name,
                'completed'      => $done,
                'pending'        => (int) ($pending[$dept->id] ?? 0),
                'reviews'        => $stat ? (int) $stat->reviews : 0,
                'avg_score'      => $stat && $stat->reviews ? round((float) $stat->avg_score, 1) : null,
                'last_reviewed'  => $stat->last_reviewed_at ?? null,
                'criteria'       => (int) ($criteria[$dept->id] ?? 0),
                'reviewers'      => (int) ($reviewers[$dept->id] ?? 0),
                // A department that has never completed a task has nothing to review;
                // showing it with a 0% score would read as a failing grade.
                'is_active'      => $done > 0,
            ];
        }

        usort($rows, function ($a, $b) {
            if ($a['is_active'] !== $b['is_active']) {
                return $a['is_active'] ? -1 : 1;
            }

            return $b['completed'] <=> $a['completed'];
        });

        return $rows;
    }

    /** Completed tasks in this department that nobody has reviewed yet, newest first. */
    public function pendingTasks(int $departmentId, int $limit = 100)
    {
        return $this->completedTasksQuery()
            ->where('projects.department_id', $departmentId)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('task_quality_reviews')
                  ->whereColumn('task_quality_reviews.project_task_id', 'project_tasks.id');
            })
            ->select(
                'project_tasks.id',
                'project_tasks.name',
                'project_tasks.assign_to',
                'project_tasks.end_date',
                'project_tasks.marked_at',
                'project_tasks.priority',
                'project_tasks.project_id',
                'projects.project_name'
            )
            ->orderByDesc('project_tasks.marked_at')
            ->limit($limit)
            ->get();
    }

    /** Reviews already given in this department, newest first. */
    public function reviews(int $departmentId, int $createdBy, int $limit = 100)
    {
        return TaskQualityReview::with(['task', 'reviewer'])
            ->where('department_id', $departmentId)
            ->where('created_by', $createdBy)
            ->orderByDesc('reviewed_at')
            ->limit($limit)
            ->get();
    }

    /** Headline numbers for one department. */
    public function summary(int $departmentId, int $createdBy): array
    {
        $reviews = TaskQualityReview::where('department_id', $departmentId)
            ->where('created_by', $createdBy);

        $count = (clone $reviews)->count();

        $completed = (int) ($this->completedTaskCountsByDepartment()[$departmentId] ?? 0);
        $pending   = (int) ($this->pendingTaskCountsByDepartment()[$departmentId] ?? 0);

        $last30 = (clone $reviews)->where('reviewed_at', '>=', now()->subDays(30))->avg('total_score');
        $prev30 = (clone $reviews)
            ->whereBetween('reviewed_at', [now()->subDays(60), now()->subDays(30)])
            ->avg('total_score');

        return [
            'completed'      => $completed,
            'pending'        => $pending,
            'reviews'        => $count,
            'avg_score'      => $count ? round((float) (clone $reviews)->avg('total_score'), 1) : null,
            'on_time_rate'   => $count
                ? round((clone $reviews)->where('was_on_time', true)->count() / $count * 100, 1)
                : null,
            'avg_last_30'    => $last30 !== null ? round((float) $last30, 1) : null,
            'avg_prev_30'    => $prev30 !== null ? round((float) $prev30, 1) : null,
            // Reviewed / completed, so an untouched department reads as 0% covered
            // rather than as having no quality problem.
            'coverage'       => $completed > 0 ? round($count / $completed * 100, 1) : null,
        ];
    }

    /** Per-employee scores in one department — who the reviews actually landed on. */
    public function byEmployee(int $departmentId, int $createdBy): array
    {
        $rows = [];

        foreach ($this->reviews($departmentId, $createdBy, 1000) as $review) {
            foreach (array_filter(array_map('intval', explode(',', (string) $review->assignee_ids))) as $uid) {
                $rows[$uid] ??= ['user_id' => $uid, 'reviews' => 0, 'total' => 0.0, 'on_time' => 0];
                $rows[$uid]['reviews']++;
                $rows[$uid]['total'] += $review->total_score;
                $rows[$uid]['on_time'] += $review->was_on_time ? 1 : 0;
            }
        }

        if (!$rows) {
            return [];
        }

        $names = User::whereIn('id', array_keys($rows))->pluck('name', 'id');

        foreach ($rows as $uid => &$r) {
            $r['name']         = $names[$uid] ?? __('Unknown');
            $r['avg_score']    = round($r['total'] / $r['reviews'], 1);
            $r['on_time_rate'] = round($r['on_time'] / $r['reviews'] * 100);
        }
        unset($r);

        usort($rows, fn($a, $b) => $b['avg_score'] <=> $a['avg_score']);

        return $rows;
    }

    /** Reviewers named for a department, as users. */
    public function reviewers(int $departmentId)
    {
        $ids = QualityDepartmentReviewer::where('department_id', $departmentId)->pluck('user_id');

        return $ids->isEmpty() ? collect() : User::whereIn('id', $ids)->orderBy('name')->get();
    }

    /**
     * May this user review this department?
     *
     * Holding 'manage quality criteria' is enough — that is the administrator who
     * configures the whole thing and needs to be able to unblock a department whose
     * named reviewer has left.
     */
    public function canReview(User $user, int $departmentId): bool
    {
        if ($user->can('manage quality criteria')) {
            return true;
        }

        if (!$user->can('review task quality')) {
            return false;
        }

        return QualityDepartmentReviewer::where('department_id', $departmentId)
            ->where('user_id', $user->id)
            ->exists();
    }

    /** Departments this user is named as a reviewer for. */
    public function departmentsUserReviews(int $userId): array
    {
        return QualityDepartmentReviewer::where('user_id', $userId)
            ->pluck('department_id')
            ->all();
    }

    /**
     * The department a task belongs to, or null when its project has none.
     * This is the join the whole feature hangs on.
     */
    public function departmentOfTask(ProjectTask $task): ?int
    {
        $id = DB::table('projects')->where('id', $task->project_id)->value('department_id');

        return $id ? (int) $id : null;
    }

    /**
     * Was the task finished by its due date? Null when either date is missing, which
     * is common here — it is offered to the reviewer as a hint, never as a score.
     */
    public function wasOnTime(ProjectTask $task): ?bool
    {
        if (empty($task->end_date) || empty($task->marked_at)) {
            return null;
        }

        return strtotime($task->marked_at) <= strtotime($task->end_date);
    }

    /* ------------------------------------------------------------------ internals */

    /**
     * Completed tasks are matched on is_complete, not on a stage named "Complete":
     * stage names are per-project here and several projects have renamed theirs.
     */
    private function completedTasksQuery()
    {
        return DB::table('project_tasks')
            ->join('projects', 'projects.id', '=', 'project_tasks.project_id')
            ->where('project_tasks.is_complete', 1)
            ->whereNotNull('projects.department_id')
            ->where('projects.department_id', '>', 0)
            // marked_at only. updated_at moves on any edit, and a historical bug in
            // ProjectTaskController wiped marked_at on every card dragged into Done,
            // so "null marked_at + recent updated_at" describes thousands of old
            // tasks rather than recent work. Including it put 1,444 rows in a queue
            // whose honest size was a couple of hundred.
            ->whereNotNull('project_tasks.marked_at')
            ->where('project_tasks.marked_at', '>=', now()->subDays(self::REVIEW_WINDOW_DAYS)->toDateString());
    }

    private ?array $completedCache = null;

    private function completedTaskCountsByDepartment(): array
    {
        return $this->completedCache ??= $this->completedTasksQuery()
            ->select('projects.department_id', DB::raw('COUNT(*) as n'))
            ->groupBy('projects.department_id')
            ->pluck('n', 'department_id')
            ->all();
    }

    private ?array $pendingCache = null;

    private function pendingTaskCountsByDepartment(): array
    {
        return $this->pendingCache ??= $this->completedTasksQuery()
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('task_quality_reviews')
                  ->whereColumn('task_quality_reviews.project_task_id', 'project_tasks.id');
            })
            ->select('projects.department_id', DB::raw('COUNT(*) as n'))
            ->groupBy('projects.department_id')
            ->pluck('n', 'department_id')
            ->all();
    }
}
