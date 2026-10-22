<?php

namespace Modules\VideoStreaming\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoQuality extends Model
{
    protected $table = 'vs_video_qualities';

    protected $fillable = [
        'video_id',
        'quality',
        'width',
        'height',
        'bitrate',
        'playlist_filename',
        'google_drive_folder_id',
        'segments_count',
        'status',
        'file_size',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'segments_count' => 'integer',
        'file_size' => 'integer',
    ];

    /**
     * Get the video that owns the quality.
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'video_id');
    }

    /**
     * Check if quality is ready.
     */
    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    /**
     * Get quality label.
     */
    public function getLabelAttribute(): string
    {
        return match($this->quality) {
            '360p' => '360p (SD)',
            '480p' => '480p (SD)',
            '720p' => '720p (HD)',
            '1080p' => '1080p (Full HD)',
            '1440p' => '1440p (2K)',
            '2160p' => '2160p (4K)',
            default => $this->quality,
        };
    }
}
