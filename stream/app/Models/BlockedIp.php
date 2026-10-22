<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockedIp extends Model
{
    protected $fillable = [
        'ip_address', 'video_id', 'reason', 'blocked_at',
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
