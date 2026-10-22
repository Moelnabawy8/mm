<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'account_id',
        'campaign_id',
        'post_id',
        'content',
        'media_url',
        'media_type',
        'status',
        'scheduled_at',
        'published_at',
        'likes',
        'comments',
        'shares',
        'reach',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(MarketingAccount::class, 'account_id');
    }

    public function campaign()
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTotalEngagementAttribute()
    {
        return $this->likes + $this->comments + $this->shares;
    }

    public function getEngagementRateAttribute()
    {
        if ($this->reach == 0) {
            return 0;
        }
        
        return ($this->total_engagement / $this->reach) * 100;
    }
}
