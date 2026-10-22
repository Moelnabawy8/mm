<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MultiPostLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'created_by',
        'account_id',
        'post_id',
        'platform',
        'status',
        'remote_post_id',
        'error_message',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(MarketingAccount::class, 'account_id');
    }

    public function post()
    {
        return $this->belongsTo(MarketingPost::class, 'post_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all logs for a specific batch (multi-post request).
     */
    public function scopeBatch($query, string $batchId)
    {
        return $query->where('batch_id', $batchId);
    }
}
