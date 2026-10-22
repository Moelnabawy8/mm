<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Repositories\Eloquent;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\PublicRelations\Entities\PrInteractionLog;
use Modules\PublicRelations\Repositories\Contracts\PrInteractionLogRepositoryInterface;

class PrInteractionLogRepository implements PrInteractionLogRepositoryInterface
{
    public function paginateForEntity(int $entityId, array $filters, int $ownerId, int $perPage): LengthAwarePaginator
    {
        $query = PrInteractionLog::query()
            ->forEntity($entityId)
            ->where('created_by', $ownerId)
            ->betweenDates($filters['date_from'] ?? null, $filters['date_to'] ?? null);

        if (! empty($filters['objective'])) {
            $query->where('objective', $filters['objective']);
        }

        return $query->orderByDesc('interaction_date')->paginate($perPage);
    }

    public function findOrFail(int $id, int $ownerId): PrInteractionLog
    {
        return PrInteractionLog::query()->where('created_by', $ownerId)->findOrFail($id);
    }

    public function latestForEntity(int $entityId): ?PrInteractionLog
    {
        return PrInteractionLog::query()
            ->forEntity($entityId)
            ->orderByDesc('interaction_date')
            ->orderByDesc('id')
            ->first();
    }

    public function create(array $data): PrInteractionLog
    {
        return PrInteractionLog::create($data);
    }

    public function update(PrInteractionLog $log, array $data): PrInteractionLog
    {
        $log->update($data);

        return $log->refresh();
    }

    public function delete(PrInteractionLog $log): bool
    {
        return (bool) $log->delete();
    }
}
