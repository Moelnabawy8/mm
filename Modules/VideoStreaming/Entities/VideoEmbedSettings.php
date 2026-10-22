<?php

namespace Modules\VideoStreaming\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VideoEmbedSettings extends Model
{
    protected $table = 'vs_video_embed_settings';

    protected $fillable = [
        'video_id',
        'embed_code',
        'embed_enabled',
        'player_color',
        'show_controls',
        'autoplay',
        'loop',
        'muted',
        'logo_url',
        'logo_position',
        'logo_link',
        'allow_fullscreen',
        'allow_pip',
        'show_quality_selector',
        'show_speed_selector',
        'default_width',
        'default_height',
        'aspect_ratio',
    ];

    protected $casts = [
        'embed_enabled' => 'boolean',
        'show_controls' => 'boolean',
        'autoplay' => 'boolean',
        'loop' => 'boolean',
        'muted' => 'boolean',
        'allow_fullscreen' => 'boolean',
        'allow_pip' => 'boolean',
        'show_quality_selector' => 'boolean',
        'show_speed_selector' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($settings) {
            if (empty($settings->embed_code)) {
                $settings->embed_code = Str::random(12);
            }
        });
    }

    /**
     * Get the video that owns the embed settings.
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'video_id');
    }

    /**
     * Get player config as JSON.
     */
    public function getPlayerConfigAttribute(): array
    {
        return [
            'color' => $this->player_color,
            'controls' => $this->show_controls,
            'autoplay' => $this->autoplay,
            'loop' => $this->loop,
            'muted' => $this->muted,
            'fullscreen' => $this->allow_fullscreen,
            'pip' => $this->allow_pip,
            'qualitySelector' => $this->show_quality_selector,
            'speedSelector' => $this->show_speed_selector,
            'logo' => $this->logo_url ? [
                'url' => $this->logo_url,
                'position' => $this->logo_position,
                'link' => $this->logo_link,
            ] : null,
        ];
    }
}
