<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskEvent extends Model
{
    protected $table = 'task_events';

    /** Append-only — no updated_at; created_at acts as occurred_at. */
    public $timestamps = false;

    protected $fillable = [
        'task_log_id',
        'task_id',
        'user_id',
        'event_type',
        'occurred_at',
        'meta',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'meta'        => 'array',
    ];

    public function taskLog(): BelongsTo
    {
        return $this->belongsTo(TaskLog::class, 'task_log_id');
    }
}
