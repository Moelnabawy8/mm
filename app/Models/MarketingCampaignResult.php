<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingCampaignResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'date',
        'impressions',
        'clicks',
        'reach',
        'engagement',
        'conversions',
        'spend',
        'cpc',
        'cpm',
        'ctr',
        'conversion_rate',
        'metadata',
    ];

    protected $casts = [
        'date' => 'date',
        'metadata' => 'array',
        'spend' => 'decimal:2',
        'cpc' => 'decimal:4',
        'cpm' => 'decimal:4',
        'ctr' => 'decimal:4',
        'conversion_rate' => 'decimal:4',
    ];

    public function campaign()
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }
}
