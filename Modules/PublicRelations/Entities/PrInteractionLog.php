<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\PublicRelations\Concerns\HasCustomFields;
use Modules\PublicRelations\Concerns\HasPrAttachments;
use Modules\PublicRelations\Contracts\Attachable;
use Modules\PublicRelations\Contracts\Fieldable;
use Modules\PublicRelations\Enums\PrObjective;

class PrInteractionLog extends Model implements Attachable, Fieldable
{
    use HasCustomFields;
    use HasPrAttachments;

    protected $table = 'pr_interaction_logs';

    protected $fillable = [
        'pr_entity_id',
        'interaction_date',
        'objective',
        'current_action',
        'previous_action_id',
        'next_action',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'interaction_date' => 'date',
        'objective' => PrObjective::class,
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(PrEntity::class, 'pr_entity_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The log entry that directly preceded this one for the same entity.
     */
    public function previousAction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_action_id');
    }

    /**
     * Any log entries that were recorded with this one as their previous action.
     */
    public function subsequentActions(): HasMany
    {
        return $this->hasMany(self::class, 'previous_action_id');
    }

    public function scopeForEntity(Builder $query, int $entityId): Builder
    {
        return $query->where('pr_entity_id', $entityId);
    }

    public function scopeBetweenDates(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from && $to) {
            return $query->whereBetween('interaction_date', [$from, $to]);
        }

        if ($from) {
            return $query->where('interaction_date', '>=', $from);
        }

        if ($to) {
            return $query->where('interaction_date', '<=', $to);
        }

        return $query;
    }
}
