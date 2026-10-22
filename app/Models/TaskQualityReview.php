<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A completed task, scored against its department's criteria.
 *
 * department_id, project_id and criteria_snapshot are copies taken at review time.
 * A project can be refiled under another department and a criterion renamed or
 * retired; a score belongs to the department that gave it, judged by the yardstick
 * that existed then, and must stay readable afterwards.
 */
class TaskQualityReview extends Model
{
    protected $table = 'task_quality_reviews';

    protected $fillable = [
        'project_task_id',
        'department_id',
        'project_id',
        'reviewer_id',
        'assignee_ids',
        'scores',
        'criteria_snapshot',
        'total_score',
        'was_on_time',
        'notes',
        'reviewed_at',
        'created_by',
    ];

    protected $casts = [
        'scores'            => 'array',
        'criteria_snapshot' => 'array',
        'total_score'       => 'float',
        'was_on_time'       => 'boolean',
        'reviewed_at'       => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(ProjectTask::class, 'project_task_id', 'id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id', 'id');
    }

    /** The users this task was assigned to, from the comma list copied at review time. */
    public function assignees()
    {
        $ids = array_filter(array_map('intval', explode(',', (string) $this->assignee_ids)));

        return $ids ? User::whereIn('id', $ids)->get() : collect();
    }

    /**
     * Weighted percentage for a set of scores.
     *
     * Criteria carry their own weight and their own top-of-scale, so the total is
     * sum(score x weight) over sum(max x weight) — not a plain average, which would
     * let a 1-to-3 criterion outrank a 1-to-5 one.
     */
    public static function computeTotal(array $scores, $criteria): float
    {
        $earned = 0;
        $possible = 0;

        foreach ($criteria as $c) {
            $id = is_array($c) ? $c['id'] : $c->id;

            if (!array_key_exists($id, $scores) || $scores[$id] === null || $scores[$id] === '') {
                continue;
            }

            $weight = (int) (is_array($c) ? $c['weight'] : $c->weight);
            $max    = (int) (is_array($c) ? $c['max_score'] : $c->max_score);

            if ($weight < 1 || $max < 1) {
                continue;
            }

            $earned   += min((int) $scores[$id], $max) * $weight;
            $possible += $max * $weight;
        }

        return $possible > 0 ? round($earned / $possible * 100, 2) : 0.0;
    }

    public function getScoreBandAttribute(): string
    {
        $s = $this->total_score;

        if ($s >= 85) {
            return 'success';
        }

        if ($s >= 70) {
            return 'info';
        }

        if ($s >= 50) {
            return 'warning';
        }

        return 'danger';
    }

    public function getScoreLabelAttribute(): string
    {
        $s = $this->total_score;

        if ($s >= 85) {
            return 'Excellent';
        }

        if ($s >= 70) {
            return 'Good';
        }

        if ($s >= 50) {
            return 'Needs improvement';
        }

        return 'Poor';
    }
}
