<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\PublicRelations\Concerns\HasCustomFields;
use Modules\PublicRelations\Concerns\HasPrAttachments;
use Modules\PublicRelations\Contracts\Attachable;
use Modules\PublicRelations\Contracts\Fieldable;
use Modules\PublicRelations\Enums\PrMilestoneEventType;
use Modules\PublicRelations\Enums\PrMilestoneStatus;

class PrMilestone extends Model implements Attachable, Fieldable
{
    use HasCustomFields;
    use HasPrAttachments;

    protected $table = 'pr_milestones';

    protected $fillable = [
        'pr_entity_id',
        'event_type',
        'event_date',
        'status',
        'created_by',
    ];

    protected $casts = [
        'event_date' => 'date',
        'event_type' => PrMilestoneEventType::class,
        'status' => PrMilestoneStatus::class,
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(PrEntity::class, 'pr_entity_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOfStatus(Builder $query, PrMilestoneStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof PrMilestoneStatus ? $status->value : $status);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', PrMilestoneStatus::Upcoming->value)
            ->where('event_date', '>=', now()->toDateString());
    }
}
