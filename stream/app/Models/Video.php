<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Video extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'title', 'description', 'duration', 'resolution',
        'storage_driver', 'file_path', 'hls_path', 'encryption_key_path',
        'thumbnail_path', 'visibility', 'status', 'created_by',
        // Enterprise security fields
        'max_concurrent_streams', 'max_total_views', 'expires_at',
        'token_duration', 'ip_binding_enabled', 'fingerprint_lock',
        'geo_restriction_enabled', 'allowed_countries', 'key_rotation_enabled',
        'frozen',
        // Watermark
        'watermark_text', 'watermark_type', 'watermark_opacity',
        'watermark_position', 'watermark_animation', 'watermark_enabled',
        // Counters & preset
        'total_views_count', 'security_preset_id',
    ];

    protected function casts(): array
    {
        return [
            'duration' => 'integer',
            'max_concurrent_streams' => 'integer',
            'max_total_views' => 'integer',
            'token_duration' => 'integer',
            'total_views_count' => 'integer',
            'expires_at' => 'datetime',
            'ip_binding_enabled' => 'boolean',
            'fingerprint_lock' => 'boolean',
            'geo_restriction_enabled' => 'boolean',
            'key_rotation_enabled' => 'boolean',
            'frozen' => 'boolean',
            'watermark_enabled' => 'boolean',
            'watermark_opacity' => 'decimal:2',
            'allowed_countries' => 'array',
        ];
    }

    // Relationships
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allowedDomains(): HasMany
    {
        return $this->hasMany(AllowedDomain::class);
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(VideoAccessLog::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ActiveSession::class);
    }

    public function activeSessions(): HasMany
    {
        return $this->hasMany(ActiveSession::class)->where('status', 'active');
    }

    public function securityPreset(): BelongsTo
    {
        return $this->belongsTo(SecurityPreset::class);
    }

    public function blockedIps(): HasMany
    {
        return $this->hasMany(BlockedIp::class);
    }

    public function blockedFingerprints(): HasMany
    {
        return $this->hasMany(BlockedFingerprint::class);
    }

    // Helpers
    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['ready', 'active']) && !$this->frozen;
    }

    public function isFrozen(): bool
    {
        return $this->frozen;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function hasReachedViewLimit(): bool
    {
        return $this->max_total_views > 0 && $this->total_views_count >= $this->max_total_views;
    }

    public function hasReachedSessionLimit(): bool
    {
        if ($this->max_concurrent_streams <= 0) return false;
        return $this->activeSessions()->count() >= $this->max_concurrent_streams;
    }

    public function getHashAttribute(): string
    {
        return base64_encode(encrypt($this->id));
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('frozen', false)->whereIn('status', ['ready', 'active']);
    }

    public function scopeFrozen($query)
    {
        return $query->where('frozen', true);
    }
}
