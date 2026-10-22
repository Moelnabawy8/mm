<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskLog extends Model
{
    protected $table = 'task_logs';

    protected $fillable = [
        'user_id',
        'task_id',
        'start_time',
        'end_time',
        'active_seconds',
        'idle_seconds',
        'status',
        'last_heartbeat_at',
    ];

    protected $casts = [
        'start_time'        => 'datetime',
        'end_time'          => 'datetime',
        'last_heartbeat_at' => 'datetime',
        'active_seconds'    => 'integer',
        'idle_seconds'      => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The underlying domain task (project_tasks). We point at the existing
     * ProjectTask model rather than creating a parallel one.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'task_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TaskEvent::class, 'task_log_id');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['active', 'paused']);
    }
}
