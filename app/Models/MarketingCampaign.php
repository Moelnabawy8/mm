<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'account_id',
        'campaign_name',
        'campaign_id',
        'platform',
        'objective',
        'status',
        'budget',
        'currency',
        'start_date',
        'end_date',
        'description',
        'targeting',
        'metadata',
    ];

    protected $casts = [
        'targeting' => 'array',
        'metadata' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'budget' => 'decimal:2',
    ];

    public function account()
    {
        return $this->belongsTo(MarketingAccount::class, 'account_id');
    }

    public function results()
    {
        return $this->hasMany(MarketingCampaignResult::class, 'campaign_id');
    }

    public function posts()
    {
        return $this->hasMany(MarketingPost::class, 'campaign_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTotalSpentAttribute()
    {
        return $this->results()->sum('spend');
    }

    public function getTotalImpressionsAttribute()
    {
        return $this->results()->sum('impressions');
    }

    public function getTotalClicksAttribute()
    {
        return $this->results()->sum('clicks');
    }

    public function getTotalConversionsAttribute()
    {
        return $this->results()->sum('conversions');
    }

    public function getAverageCtrAttribute()
    {
        $totalImpressions = $this->total_impressions;
        $totalClicks = $this->total_clicks;
        
        if ($totalImpressions == 0) {
            return 0;
        }
        
        return ($totalClicks / $totalImpressions) * 100;
    }

    public function getAverageCpcAttribute()
    {
        $totalSpent = $this->total_spent;
        $totalClicks = $this->total_clicks;
        
        if ($totalClicks == 0) {
            return 0;
        }
        
        return $totalSpent / $totalClicks;
    }

    public function isActive()
    {
        return $this->status === 'active';
    }
}
