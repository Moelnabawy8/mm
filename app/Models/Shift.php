<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'break_duration',
        'working_days',
        'status',
        'created_by',
    ];

    public function employeeShifts()
    {
        return $this->hasMany(EmployeeShift::class);
    }

    public function getWorkingDaysArrayAttribute(): array
    {
        return $this->working_days ? explode(',', $this->working_days) : [];
    }

    public function getDurationAttribute(): string
    {
        $start  = \Carbon\Carbon::createFromTimeString($this->start_time);
        $end    = \Carbon\Carbon::createFromTimeString($this->end_time);
        $diff   = $start->diffInMinutes($end) - $this->break_duration;
        $hours  = intdiv($diff, 60);
        $mins   = $diff % 60;
        return "{$hours}h " . ($mins ? "{$mins}m" : '');
    }
}
