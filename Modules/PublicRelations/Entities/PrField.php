<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\PublicRelations\Enums\PrFieldModule;
use Modules\PublicRelations\Enums\PrFieldType;

class PrField extends Model
{
    protected $table = 'pr_fields';

    protected $fillable = [
        'module',
        'label',
        'key',
        'type',
        'options',
        'is_required',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'module' => PrFieldModule::class,
        'type' => PrFieldType::class,
        'options' => 'array',
        'is_required' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function values(): HasMany
    {
        return $this->hasMany(PrFieldValue::class);
    }

    public function scopeForModule(Builder $query, PrFieldModule|string $module): Builder
    {
        return $query->where('module', $module instanceof PrFieldModule ? $module->value : $module);
    }

    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('created_by', $userId);
    }
}
