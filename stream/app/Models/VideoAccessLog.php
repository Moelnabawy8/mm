<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoAccessLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'video_id', 'ip_address', 'fingerprint', 'user_agent',
        'referer', 'country', 'session_token', 'access_time',
        'blocked', 'watch_duration',
    ];

    protected function casts(): array
    {
        return [
            'access_time' => 'datetime',
            'blocked' => 'boolean',
            'watch_duration' => 'integer',
        ];
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
}
