<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\PublicRelations\Concerns\HasCustomFields;
use Modules\PublicRelations\Concerns\HasPrAttachments;
use Modules\PublicRelations\Contracts\Attachable;
use Modules\PublicRelations\Contracts\Fieldable;
use Modules\PublicRelations\Enums\PrEntityStatus;
use Modules\PublicRelations\Enums\PrEntityType;
use Modules\PublicRelations\Enums\PrRegion;

class PrEntity extends Model implements Attachable, Fieldable
{
    use HasCustomFields;
    use HasPrAttachments;
    use SoftDeletes;

    protected $table = 'pr_entities';

    protected $fillable = [
        'name',
        'type',
        'region',
        'contact_person',
        'position',
        'phone',
        'email',
        'status',
        'created_by',
    ];

    protected $casts = [
        'type' => PrEntityType::class,
        'region' => PrRegion::class,
        'status' => PrEntityStatus::class,
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function interactionLogs(): HasMany
    {
        return $this->hasMany(PrInteractionLog::class)->orderByDesc('interaction_date');
    }

    public function latestInteractionLog(): HasOne
    {
        return $this->hasOne(PrInteractionLog::class)->latestOfMany('interaction_date');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(PrMilestone::class)->orderBy('event_date');
    }

    public function scopeOfType(Builder $query, PrEntityType|string $type): Builder
    {
        return $query->where('type', $type instanceof PrEntityType ? $type->value : $type);
    }

    public function scopeOfStatus(Builder $query, PrEntityStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof PrEntityStatus ? $status->value : $status);
    }

    public function scopeOfRegion(Builder $query, PrRegion|string $region): Builder
    {
        return $query->where('region', $region instanceof PrRegion ? $region->value : $region);
    }

    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('created_by', $userId);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('contact_person', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%");
        });
    }
}
