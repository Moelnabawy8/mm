<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Repositories\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\PublicRelations\Entities\PrEntity;

interface PrEntityRepositoryInterface
{
    /**
     * @param array{type?: string, region?: string, status?: string, date_from?: string, date_to?: string, search?: string} $filters
     */
    public function paginate(array $filters, int $ownerId, int $perPage): LengthAwarePaginator;

    public function findOrFail(int $id, int $ownerId): PrEntity;

    public function create(array $data): PrEntity;

    public function update(PrEntity $entity, array $data): PrEntity;

    public function delete(PrEntity $entity): bool;
}
