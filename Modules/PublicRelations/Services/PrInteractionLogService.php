<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\PublicRelations\Entities\PrInteractionLog;
use Modules\PublicRelations\Enums\PrFieldModule;
use Modules\PublicRelations\Repositories\Contracts\PrEntityRepositoryInterface;
use Modules\PublicRelations\Repositories\Contracts\PrInteractionLogRepositoryInterface;

class PrInteractionLogService
{
    public function __construct(
        private readonly PrInteractionLogRepositoryInterface $logs,
        private readonly PrEntityRepositoryInterface $entities,
        private readonly PrCustomFieldService $customFields,
    ) {
    }

    public function listForEntity(int $entityId, array $filters, int $ownerId, int $perPage): LengthAwarePaginator
    {
        $this->entities->findOrFail($entityId, $ownerId);

        return $this->logs->paginateForEntity($entityId, $filters, $ownerId, $perPage);
    }

    public function find(int $id, int $ownerId): PrInteractionLog
    {
        return $this->logs->findOrFail($id, $ownerId);
    }

    /**
     * Create a pipeline log row for an entity, automatically linking the
     * previous action from the last recorded log for that entity.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $customFieldInput
     */
    public function create(int $entityId, array $data, array $customFieldInput, int $ownerId): PrInteractionLog
    {
        $this->entities->findOrFail($entityId, $ownerId);

        return DB::transaction(function () use ($entityId, $data, $customFieldInput, $ownerId) {
            $previous = $this->logs->latestForEntity($entityId);

            $log = $this->logs->create([
                ...$data,
                'pr_entity_id' => $entityId,
                'previous_action_id' => $previous?->id,
                'created_by' => $ownerId,
            ]);

            $this->customFields->sync($log, PrFieldModule::InteractionLog, $ownerId, $customFieldInput);

            return $log;
        });
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $customFieldInput
     */
    public function update(int $id, array $data, array $customFieldInput, int $ownerId): PrInteractionLog
    {
        return DB::transaction(function () use ($id, $data, $customFieldInput, $ownerId) {
            $log = $this->logs->findOrFail($id, $ownerId);
            $log = $this->logs->update($log, $data);

            $this->customFields->sync($log, PrFieldModule::InteractionLog, $ownerId, $customFieldInput);

            return $log;
        });
    }

    public function delete(int $id, int $ownerId): bool
    {
        return $this->logs->delete($this->logs->findOrFail($id, $ownerId));
    }
}
