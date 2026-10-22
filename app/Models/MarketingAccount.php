<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'platform',
        'account_name',
        'account_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'page_id',
        'page_name',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'token_expires_at' => 'datetime',
    ];

    public function campaigns()
    {
        return $this->hasMany(MarketingCampaign::class, 'account_id');
    }

    public function posts()
    {
        return $this->hasMany(MarketingPost::class, 'account_id');
    }

    public function insights()
    {
        return $this->hasMany(MarketingInsight::class, 'account_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isTokenExpired()
    {
        if (!$this->token_expires_at) {
            return false;
        }
        return $this->token_expires_at->isPast();
    }

    public function getPlatformIconAttribute()
    {
        $icons = [
            'facebook' => 'ti ti-brand-facebook',
            'instagram' => 'ti ti-brand-instagram',
            'twitter' => 'ti ti-brand-twitter',
            'x' => 'ti ti-brand-x',
            'linkedin' => 'ti ti-brand-linkedin',
            'telegram' => 'ti ti-brand-telegram',
            'youtube' => 'ti ti-brand-youtube',
            'tiktok' => 'ti ti-brand-tiktok',
        ];

        return $icons[$this->platform] ?? 'ti ti-share';
    }

    public function getPlatformColorAttribute()
    {
        $colors = [
            'facebook' => '#1877F2',
            'instagram' => '#E4405F',
            'twitter' => '#1DA1F2',
            'x' => '#000000',
            'linkedin' => '#0A66C2',
            'telegram' => '#26A5E4',
            'youtube' => '#FF0000',
            'tiktok' => '#000000',
        ];

        return $colors[$this->platform] ?? '#6c757d';
    }
}
