<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Repositories\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\PublicRelations\Entities\PrInteractionLog;

interface PrInteractionLogRepositoryInterface
{
    /**
     * @param array{date_from?: string, date_to?: string, objective?: string} $filters
     */
    public function paginateForEntity(int $entityId, array $filters, int $ownerId, int $perPage): LengthAwarePaginator;

    public function findOrFail(int $id, int $ownerId): PrInteractionLog;

    public function latestForEntity(int $entityId): ?PrInteractionLog;

    public function create(array $data): PrInteractionLog;

    public function update(PrInteractionLog $log, array $data): PrInteractionLog;

    public function delete(PrInteractionLog $log): bool;
}
