<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePerformance extends Model
{
    protected $table = 'employee_performance';

    protected $fillable = [
        'user_id', 'date',
        'active_seconds', 'idle_seconds',
        'total_tasks', 'completed_tasks', 'completed_on_time',
        'productivity', 'task_completion', 'idle_ratio', 'on_time',
        'score', 'stars', 'computed_at',
    ];

    protected $casts = [
        'date'            => 'date',
        'computed_at'     => 'datetime',
        'productivity'    => 'float',
        'task_completion' => 'float',
        'idle_ratio'      => 'float',
        'on_time'         => 'float',
        'score'           => 'float',
        'stars'           => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeBetween($query, $from, $to)
    {
        return $query->whereBetween('date', [$from, $to]);
    }
}
