<?php

namespace Modules\VideoStreaming\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Video extends Model
{
    use SoftDeletes;

    protected $table = 'vs_videos';

    protected $fillable = [
        'uuid',
        'title',
        'description',
        'user_id',
        'company_id',
        'original_filename',
        'original_extension',
        'original_size',
        'duration',
        'width',
        'height',
        'google_drive_file_id',
        'google_drive_folder_id',
        'status',
        'processing_error',
        'processed_at',
        'thumbnail_path',
        'thumbnail_drive_id',
        'is_public',
        'password_protected',
        'password',
        'domain_lock_enabled',
        'allowed_domains',
        'watermark_enabled',
        'watermark_text',
        'watermark_position',
        'download_enabled',
        'share_enabled',
        'comments_enabled',
        'auto_play',
        'loop_video',
        'show_controls',
        'max_views',
        'expires_at',
        'views_count',
        'unique_views_count',
        'metadata',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'password_protected' => 'boolean',
        'domain_lock_enabled' => 'boolean',
        'watermark_enabled' => 'boolean',
        'download_enabled' => 'boolean',
        'share_enabled' => 'boolean',
        'comments_enabled' => 'boolean',
        'auto_play' => 'boolean',
        'loop_video' => 'boolean',
        'show_controls' => 'boolean',
        'allowed_domains' => 'array',
        'metadata' => 'array',
        'processed_at' => 'datetime',
        'expires_at' => 'datetime',
        'original_size' => 'integer',
        'duration' => 'integer',
        'views_count' => 'integer',
        'unique_views_count' => 'integer',
        'max_views' => 'integer',
    ];

    protected $hidden = [
        'password',
    ];

    protected $appends = [
        'formatted_duration',
        'formatted_size',
        'embed_url',
        'iframe_code',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($video) {
            if (empty($video->uuid)) {
                $video->uuid = Str::uuid()->toString();
            }
        });
    }

    /**
     * Get the qualities for the video.
     */
    public function qualities(): HasMany
    {
        return $this->hasMany(VideoQuality::class, 'video_id');
    }

    /**
     * Get the encryption keys for the video.
     */
    public function encryptionKeys(): HasMany
    {
        return $this->hasMany(VideoEncryptionKey::class, 'video_id');
    }

    /**
     * Get the active encryption key.
     */
    public function activeEncryptionKey(): HasOne
    {
        return $this->hasOne(VideoEncryptionKey::class, 'video_id')
            ->where('is_active', true)
            ->latest();
    }

    /**
     * Get the access tokens for the video.
     */
    public function accessTokens(): HasMany
    {
        return $this->hasMany(VideoAccessToken::class, 'video_id');
    }

    /**
     * Get the analytics for the video.
     */
    public function analytics(): HasMany
    {
        return $this->hasMany(VideoAnalytics::class, 'video_id');
    }

    /**
     * Get the embed settings for the video.
     */
    public function embedSettings(): HasOne
    {
        return $this->hasOne(VideoEmbedSettings::class, 'video_id');
    }

    /**
     * Get the user that owns the video.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    /**
     * Check if video is ready for streaming.
     */
    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    /**
     * Check if video is being processed.
     */
    public function isProcessing(): bool
    {
        return in_array($this->status, ['uploading', 'uploaded', 'processing']);
    }

    /**
     * Get formatted duration.
     */
    public function getFormattedDurationAttribute(): string
    {
        if (!$this->duration) {
            return '00:00';
        }

        $hours = floor($this->duration / 3600);
        $minutes = floor(($this->duration % 3600) / 60);
        $seconds = $this->duration % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    /**
     * Get formatted file size.
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->original_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get embed URL.
     */
    public function getEmbedUrlAttribute(): string
    {
        return route('video.embed', ['uuid' => $this->uuid]);
    }

    /**
     * Get iframe code.
     */
    public function getIframeCodeAttribute(): string
    {
        $url = $this->embed_url;
        return '<iframe src="' . $url . '" width="100%" height="100%" frameborder="0" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>';
    }

    /**
     * Check if video requires password.
     */
    public function requiresPassword(): bool
    {
        return $this->password_protected && !empty($this->password);
    }

    /**
     * Verify video password.
     */
    public function verifyPassword(string $password): bool
    {
        if (!$this->requiresPassword()) {
            return true;
        }

        return \Illuminate\Support\Facades\Hash::check($password, $this->password);
    }

    /**
     * Set password attribute with hashing.
     */
    public function setPasswordAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['password'] = \Illuminate\Support\Facades\Hash::make($value);
        }
    }

    /**
     * Check if video has expired.
     */
    public function hasExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Check if video has reached max views.
     */
    public function hasReachedMaxViews(): bool
    {
        if (!$this->max_views) {
            return false;
        }

        return $this->views_count >= $this->max_views;
    }

    /**
     * Check if video is accessible.
     */
    public function isAccessible(): bool
    {
        return $this->isReady() && !$this->hasExpired() && !$this->hasReachedMaxViews();
    }

    /**
     * Get company relationship.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'company_id');
    }
}
