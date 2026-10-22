<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingInsight extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'date',
        'followers',
        'page_views',
        'profile_visits',
        'total_engagement',
        'engagement_rate',
        'demographics',
        'metadata',
    ];

    protected $casts = [
        'date' => 'date',
        'demographics' => 'array',
        'metadata' => 'array',
        'engagement_rate' => 'decimal:4',
    ];

    public function account()
    {
        return $this->belongsTo(MarketingAccount::class, 'account_id');
    }
}
