<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\PublicRelations\Entities\PrMilestone;
use Modules\PublicRelations\Enums\PrFieldModule;
use Modules\PublicRelations\Repositories\Contracts\PrEntityRepositoryInterface;
use Modules\PublicRelations\Repositories\Contracts\PrMilestoneRepositoryInterface;

class PrMilestoneService
{
    public function __construct(
        private readonly PrMilestoneRepositoryInterface $milestones,
        private readonly PrEntityRepositoryInterface $entities,
        private readonly PrCustomFieldService $customFields,
    ) {
    }

    public function list(array $filters, int $ownerId, int $perPage): LengthAwarePaginator
    {
        return $this->milestones->paginate($filters, $ownerId, $perPage);
    }

    public function find(int $id, int $ownerId): PrMilestone
    {
        return $this->milestones->findOrFail($id, $ownerId);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $customFieldInput
     */
    public function create(int $entityId, array $data, array $customFieldInput, int $ownerId): PrMilestone
    {
        $this->entities->findOrFail($entityId, $ownerId);

        return DB::transaction(function () use ($entityId, $data, $customFieldInput, $ownerId) {
            $milestone = $this->milestones->create([
                ...$data,
                'pr_entity_id' => $entityId,
                'created_by' => $ownerId,
            ]);

            $this->customFields->sync($milestone, PrFieldModule::Milestone, $ownerId, $customFieldInput);

            return $milestone;
        });
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $customFieldInput
     */
    public function update(int $id, array $data, array $customFieldInput, int $ownerId): PrMilestone
    {
        return DB::transaction(function () use ($id, $data, $customFieldInput, $ownerId) {
            $milestone = $this->milestones->findOrFail($id, $ownerId);
            $milestone = $this->milestones->update($milestone, $data);

            $this->customFields->sync($milestone, PrFieldModule::Milestone, $ownerId, $customFieldInput);

            return $milestone;
        });
    }

    public function delete(int $id, int $ownerId): bool
    {
        return $this->milestones->delete($this->milestones->findOrFail($id, $ownerId));
    }
}
