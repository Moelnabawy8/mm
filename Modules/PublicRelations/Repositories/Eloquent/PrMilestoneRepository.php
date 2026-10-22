<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Repositories\Eloquent;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\PublicRelations\Entities\PrMilestone;
use Modules\PublicRelations\Repositories\Contracts\PrMilestoneRepositoryInterface;

class PrMilestoneRepository implements PrMilestoneRepositoryInterface
{
    public function paginate(array $filters, int $ownerId, int $perPage): LengthAwarePaginator
    {
        $query = PrMilestone::query()->where('created_by', $ownerId);

        if (! empty($filters['entity_id'])) {
            $query->where('pr_entity_id', $filters['entity_id']);
        }

        if (! empty($filters['status'])) {
            $query->ofStatus($filters['status']);
        }

        if (! empty($filters['date_from']) && ! empty($filters['date_to'])) {
            $query->whereBetween('event_date', [$filters['date_from'], $filters['date_to']]);
        } elseif (! empty($filters['date_from'])) {
            $query->where('event_date', '>=', $filters['date_from']);
        } elseif (! empty($filters['date_to'])) {
            $query->where('event_date', '<=', $filters['date_to']);
        }

        return $query->orderBy('event_date')->paginate($perPage);
    }

    public function findOrFail(int $id, int $ownerId): PrMilestone
    {
        return PrMilestone::query()->where('created_by', $ownerId)->findOrFail($id);
    }

    public function create(array $data): PrMilestone
    {
        return PrMilestone::create($data);
    }

    public function update(PrMilestone $milestone, array $data): PrMilestone
    {
        $milestone->update($data);

        return $milestone->refresh();
    }

    public function delete(PrMilestone $milestone): bool
    {
        return (bool) $milestone->delete();
    }
}
