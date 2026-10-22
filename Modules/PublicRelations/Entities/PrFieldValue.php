<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PrFieldValue extends Model
{
    protected $table = 'pr_field_values';

    protected $fillable = [
        'pr_field_id',
        'fieldable_id',
        'fieldable_type',
        'value',
    ];

    public function field(): BelongsTo
    {
        return $this->belongsTo(PrField::class, 'pr_field_id');
    }

    public function fieldable(): MorphTo
    {
        return $this->morphTo();
    }
}
