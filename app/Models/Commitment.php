<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Something an employee or a department owes a named person by a date.
 *
 * One model for both task 2 (commitments toward supervisors) and task 3 (weekly
 * commitments per department/employee) — see the migration for why they are the same
 * object rather than two.
 */
class Commitment extends Model
{
    public const STATUS_OPEN      = 'open';
    public const STATUS_DONE      = 'done';
    public const STATUS_MISSED    = 'missed';
    public const STATUS_CANCELLED = 'cancelled';

    public const CADENCE_ONCE   = 'once';
    public const CADENCE_WEEKLY = 'weekly';

    protected $fillable = [
        'title',
        'description',
        'user_id',
        'department_id',
        'owed_to_id',
        'cadence',
        'due_date',
        'week_start',
        'status',
        'progress',
        'completed_at',
        'recurs',
        'recurs_from_id',
        'meeting_id',
        'created_by',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'week_start'   => 'date',
        'completed_at' => 'datetime',
        'recurs'       => 'boolean',
        'progress'     => 'integer',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'id');
    }

    public function owedTo()
    {
        return $this->belongsTo(User::class, 'owed_to_id', 'id');
    }

    public function meeting()
    {
        return $this->belongsTo(Meeting::class, 'meeting_id', 'id');
    }

    public function updates()
    {
        return $this->hasMany(CommitmentUpdate::class, 'commitment_id', 'id')->orderByDesc('created_at');
    }

    /* ---------------------------------------------------------------- scopes */

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [self::STATUS_OPEN]);
    }

    /** Still countable: cancelled ones were withdrawn and should not score against anyone. */
    public function scopeCounted($query)
    {
        return $query->where('status', '!=', self::STATUS_CANCELLED);
    }

    public function scopeOwedTo($query, $userId)
    {
        return $query->where('owed_to_id', $userId);
    }

    public function scopeOwnedBy($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForWeek($query, $weekStart)
    {
        return $query->where('week_start', $weekStart);
    }

    /* -------------------------------------------------------------- helpers */

    /** Monday of the week a date falls in — the one definition of "a week" here. */
    public static function weekStart($date = null): string
    {
        return \Carbon\Carbon::parse($date ?: now())->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();
    }

    /** The date this commitment is actually judged against, whatever its cadence. */
    public function getDeadlineAttribute()
    {
        if ($this->due_date) {
            return $this->due_date;
        }

        // A weekly commitment is due at the end of its week.
        return $this->week_start
            ? \Carbon\Carbon::parse($this->week_start)->endOfWeek(\Carbon\Carbon::SUNDAY)
            : null;
    }

    public function getIsOverdueAttribute(): bool
    {
        if ($this->status !== self::STATUS_OPEN) {
            return false;
        }

        $deadline = $this->deadline;

        return $deadline !== null && $deadline->endOfDay()->isPast();
    }

    /**
     * Days until the deadline; negative once it has passed. Cast to int because
     * diffInDays returns a float and a fractional "3.87 days late" is unreadable.
     */
    public function getDaysLeftAttribute(): ?int
    {
        $deadline = $this->deadline;

        return $deadline === null ? null : (int) now()->startOfDay()->diffInDays($deadline->startOfDay(), false);
    }

    public function getStatusBadgeAttribute(): string
    {
        if ($this->status === self::STATUS_DONE) {
            return 'success';
        }

        if ($this->status === self::STATUS_MISSED) {
            return 'danger';
        }

        if ($this->status === self::STATUS_CANCELLED) {
            return 'secondary';
        }

        return $this->is_overdue ? 'danger' : ($this->days_left !== null && $this->days_left <= 2 ? 'warning' : 'info');
    }

    /** Who owes this, as a display string — an employee or a whole department. */
    public function getOwnerLabelAttribute(): string
    {
        if ($this->user_id) {
            return $this->owner->name ?? __('Unknown');
        }

        if ($this->department_id) {
            return ($this->department->name ?? __('Unknown')) . ' — ' . __('department');
        }

        return __('Unassigned');
    }
}
