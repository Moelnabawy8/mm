<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_HELD      = 'held';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'branch_id',
        'department_id',
        'employee_id',
        'title',
        'date',
        'time',
        'note',
        'status',
        'minutes',
        'minutes_by',
        'minutes_at',
        'created_by',
    ];

    /**
     * `date` is deliberately NOT cast.
     *
     * MeetingController's calendar feed builds its event start as
     * `$val->date . ' ' . $val->time`. Casting `date` turns that into a Carbon whose
     * __toString is 'Y-m-d H:i:s', so the concatenation produced
     * "2026-09-20 00:00:00 11:00" and FullCalendar could not parse it — a working
     * screen broken by a convenience on a model shared since 2020. Code that wants a
     * date object calls dateOn() instead.
     */
    protected $casts = [
        'minutes_at' => 'datetime',
    ];

    /** The meeting date as a Carbon, or null when it has none. */
    public function dateOn(): ?\Carbon\Carbon
    {
        if (empty($this->date)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($this->date);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Commitments raised out of this meeting's minutes. */
    public function commitments()
    {
        return $this->hasMany(Commitment::class, 'meeting_id', 'id');
    }

    public function minutesAuthor()
    {
        return $this->belongsTo(User::class, 'minutes_by', 'id');
    }

    /**
     * department_id and employee_id are longText holding comma-separated id lists, not
     * foreign keys - that is how this table was built in 2020 and changing it would
     * break the existing controller, calendar and views. These read them so the rest of
     * the code never has to remember.
     */
    public function employeeIds(): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string) $this->employee_id))));
    }

    public function departmentIds(): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string) $this->department_id))));
    }

    public function employees()
    {
        $ids = $this->employeeIds();

        return $ids ? Employee::whereIn('id', $ids)->get() : collect();
    }

    public function hasMinutes(): bool
    {
        return trim(strip_tags((string) $this->minutes)) !== '';
    }

    /**
     * A meeting whose date has passed with nothing written up. This is the number
     * task 4 exists to drive down, so it is defined once, here.
     */
    public function isAwaitingMinutes(): bool
    {
        if ($this->status === self::STATUS_CANCELLED || $this->hasMinutes()) {
            return false;
        }

        $on = $this->dateOn();

        return $on !== null && $on->endOfDay()->isPast();
    }

    public function getStatusBadgeAttribute(): string
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return 'secondary';
        }

        if ($this->hasMinutes()) {
            return 'success';
        }

        return $this->isAwaitingMinutes() ? 'danger' : 'info';
    }
}
