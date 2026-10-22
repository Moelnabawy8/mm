<?php

namespace Modules\VideoStreaming\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VideoEncryptionKey extends Model
{
    protected $table = 'vs_video_encryption_keys';

    protected $fillable = [
        'video_id',
        'key_id',
        'encryption_key',
        'iv',
        'segment_start',
        'segment_end',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'segment_start' => 'integer',
        'segment_end' => 'integer',
    ];

    protected $hidden = [
        'encryption_key',
        'iv',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($key) {
            if (empty($key->key_id)) {
                $key->key_id = Str::random(32);
            }
        });
    }

    /**
     * Get the video that owns the encryption key.
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'video_id');
    }

    /**
     * Generate new encryption key.
     */
    public static function generateKey(): string
    {
        return random_bytes(16); // AES-128 key
    }

    /**
     * Generate new IV.
     */
    public static function generateIV(): string
    {
        return random_bytes(16);
    }

    /**
     * Get key as hex string.
     */
    public function getKeyHex(): string
    {
        return bin2hex($this->encryption_key);
    }

    /**
     * Get IV as hex string.
     */
    public function getIVHex(): string
    {
        return bin2hex($this->iv);
    }
}
