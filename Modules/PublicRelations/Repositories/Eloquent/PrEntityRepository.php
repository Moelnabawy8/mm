<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Repositories\Eloquent;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\PublicRelations\Entities\PrEntity;
use Modules\PublicRelations\Repositories\Contracts\PrEntityRepositoryInterface;

class PrEntityRepository implements PrEntityRepositoryInterface
{
    public function paginate(array $filters, int $ownerId, int $perPage): LengthAwarePaginator
    {
        $query = PrEntity::query()
            ->ownedBy($ownerId)
            ->with('latestInteractionLog');

        if (! empty($filters['type'])) {
            $query->ofType($filters['type']);
        }

        if (! empty($filters['region'])) {
            $query->ofRegion($filters['region']);
        }

        if (! empty($filters['status'])) {
            $query->ofStatus($filters['status']);
        }

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            $query->whereBetween('created_at', [
                $filters['date_from'] ?? '1970-01-01',
                $filters['date_to'] ?? now()->toDateString(),
            ]);
        }

        return $query->latest('id')->paginate($perPage);
    }

    public function findOrFail(int $id, int $ownerId): PrEntity
    {
        return PrEntity::query()->ownedBy($ownerId)->findOrFail($id);
    }

    public function create(array $data): PrEntity
    {
        return PrEntity::create($data);
    }

    public function update(PrEntity $entity, array $data): PrEntity
    {
        $entity->update($data);

        return $entity->refresh();
    }

    public function delete(PrEntity $entity): bool
    {
        return (bool) $entity->delete();
    }
}
