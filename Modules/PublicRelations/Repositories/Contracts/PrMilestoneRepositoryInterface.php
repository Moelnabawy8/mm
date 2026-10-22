<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Repositories\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\PublicRelations\Entities\PrMilestone;

interface PrMilestoneRepositoryInterface
{
    /**
     * @param array{entity_id?: int, status?: string, date_from?: string, date_to?: string} $filters
     */
    public function paginate(array $filters, int $ownerId, int $perPage): LengthAwarePaginator;

    public function findOrFail(int $id, int $ownerId): PrMilestone;

    public function create(array $data): PrMilestone;

    public function update(PrMilestone $milestone, array $data): PrMilestone;

    public function delete(PrMilestone $milestone): bool;
}
