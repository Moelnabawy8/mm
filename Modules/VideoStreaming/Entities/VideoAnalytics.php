<?php

namespace Modules\VideoStreaming\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoAnalytics extends Model
{
    protected $table = 'vs_video_analytics';

    protected $fillable = [
        'video_id',
        'user_id',
        'session_id',
        'ip_address',
        'country',
        'city',
        'device_type',
        'browser',
        'os',
        'watch_duration',
        'last_position',
        'completion_rate',
        'completed',
        'pause_count',
        'seek_count',
        'quality_changes',
        'referer_url',
        'referer_domain',
    ];

    protected $casts = [
        'completed' => 'boolean',
        'watch_duration' => 'integer',
        'last_position' => 'integer',
        'completion_rate' => 'decimal:2',
        'pause_count' => 'integer',
        'seek_count' => 'integer',
        'quality_changes' => 'integer',
    ];

    /**
     * Get the video that owns the analytics.
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'video_id');
    }

    /**
     * Get the user that owns the analytics.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    /**
     * Update watch progress.
     */
    public function updateProgress(int $position, int $duration): void
    {
        $this->update([
            'watch_duration' => $this->watch_duration + 1,
            'last_position' => $position,
            'completion_rate' => $duration > 0 ? ($position / $duration) * 100 : 0,
            'completed' => $duration > 0 && $position >= ($duration * 0.9),
        ]);
    }

    /**
     * Record pause.
     */
    public function recordPause(): void
    {
        $this->increment('pause_count');
    }

    /**
     * Record seek.
     */
    public function recordSeek(): void
    {
        $this->increment('seek_count');
    }

    /**
     * Record quality change.
     */
    public function recordQualityChange(): void
    {
        $this->increment('quality_changes');
    }
}
