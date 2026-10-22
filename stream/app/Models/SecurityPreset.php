<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SecurityPreset extends Model
{
    protected $fillable = [
        'name', 'description', 'settings', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_default' => 'boolean',
        ];
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    public function applyToVideo(Video $video): void
    {
        $settings = $this->settings;
        $video->update(array_merge($settings, ['security_preset_id' => $this->id]));
    }
}
