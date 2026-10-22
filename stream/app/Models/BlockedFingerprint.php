<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockedFingerprint extends Model
{
    protected $fillable = [
        'fingerprint', 'video_id', 'reason', 'blocked_at',
    ];

    protected function casts(): array
    {
        return ['blocked_at' => 'datetime'];
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
}
