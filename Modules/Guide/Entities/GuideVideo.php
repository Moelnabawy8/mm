<?php

namespace Modules\Guide\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GuideVideo extends Model
{
    use HasFactory;

    protected $table = 'guide_videos';

    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'description',
        'video_path',
        'thumbnail',
        'duration',
        'order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(GuideCategory::class, 'category_id');
    }

    public function getVideoUrlAttribute()
    {
        return asset($this->video_path);
    }

    public function getThumbnailUrlAttribute()
    {
        if ($this->thumbnail) {
            return asset($this->thumbnail);
        }
        return asset('assets/images/video-placeholder.png');
    }
}
