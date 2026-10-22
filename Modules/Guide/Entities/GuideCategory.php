<?php

namespace Modules\Guide\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GuideCategory extends Model
{
    use HasFactory;

    protected $table = 'guide_categories';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    public function videos()
    {
        return $this->hasMany(GuideVideo::class, 'category_id');
    }

    public function documents()
    {
        return $this->hasMany(GuideDocument::class, 'category_id');
    }
}
