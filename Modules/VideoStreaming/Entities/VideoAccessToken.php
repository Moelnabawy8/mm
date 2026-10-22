<?php

namespace Modules\VideoStreaming\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Carbon\Carbon;

class VideoAccessToken extends Model
{
    protected $table = 'vs_video_access_tokens';

    protected $fillable = [
        'video_id',
        'user_id',
        'token',
        'session_id',
        'ip_address',
        'user_agent',
        'referer_domain',
        'expires_at',
        'is_revoked',
        'requests_count',
        'last_used_at',
    ];

    protected $casts = [
        'is_revoked' => 'boolean',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'requests_count' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($accessToken) {
            if (empty($accessToken->token)) {
                $accessToken->token = Str::random(64);
            }
            if (empty($accessToken->session_id)) {
                $accessToken->session_id = Str::random(32);
            }
        });
    }

    /**
     * Get the video that owns the access token.
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'video_id');
    }

    /**
     * Get the user that owns the access token.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    /**
     * Check if token is valid.
     */
    public function isValid(): bool
    {
        return !$this->is_revoked && $this->expires_at->isFuture();
    }

    /**
     * Check if token is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Revoke the token.
     */
    public function revoke(): void
    {
        $this->update(['is_revoked' => true]);
    }

    /**
     * Increment request count.
     */
    public function incrementRequests(): void
    {
        $this->increment('requests_count');
        $this->update(['last_used_at' => Carbon::now()]);
    }

    /**
     * Create token for video.
     */
    public static function createForVideo(
        Video $video,
        ?int $userId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $refererDomain = null,
        int $expiryMinutes = 60
    ): self {
        return self::create([
            'video_id' => $video->id,
            'user_id' => $userId,
            'ip_address' => $ipAddress ?? request()->ip(),
            'user_agent' => $userAgent ?? request()->userAgent(),
            'referer_domain' => $refererDomain,
            'expires_at' => Carbon::now()->addMinutes($expiryMinutes),
        ]);
    }

    /**
     * Scope to get valid tokens.
     */
    public function scopeValid($query)
    {
        return $query->where('is_revoked', false)
            ->where('expires_at', '>', Carbon::now());
    }
}
