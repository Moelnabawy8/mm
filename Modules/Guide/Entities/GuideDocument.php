<?php

namespace Modules\Guide\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GuideDocument extends Model
{
    use HasFactory;

    protected $table = 'guide_documents';

    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'content',
        'icon',
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
}
