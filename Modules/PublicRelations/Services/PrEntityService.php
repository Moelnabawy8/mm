<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\PublicRelations\Entities\PrEntity;
use Modules\PublicRelations\Enums\PrFieldModule;
use Modules\PublicRelations\Repositories\Contracts\PrEntityRepositoryInterface;

class PrEntityService
{
    public function __construct(
        private readonly PrEntityRepositoryInterface $entities,
        private readonly PrCustomFieldService $customFields,
    ) {
    }

    public function list(array $filters, int $ownerId, int $perPage): LengthAwarePaginator
    {
        return $this->entities->paginate($filters, $ownerId, $perPage);
    }

    public function find(int $id, int $ownerId): PrEntity
    {
        return $this->entities->findOrFail($id, $ownerId);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $customFieldInput
     */
    public function create(array $data, array $customFieldInput, int $ownerId): PrEntity
    {
        return DB::transaction(function () use ($data, $customFieldInput, $ownerId) {
            $entity = $this->entities->create([...$data, 'created_by' => $ownerId]);

            $this->customFields->sync($entity, PrFieldModule::Entity, $ownerId, $customFieldInput);

            return $entity;
        });
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $customFieldInput
     */
    public function update(int $id, array $data, array $customFieldInput, int $ownerId): PrEntity
    {
        return DB::transaction(function () use ($id, $data, $customFieldInput, $ownerId) {
            $entity = $this->entities->findOrFail($id, $ownerId);
            $entity = $this->entities->update($entity, $data);

            $this->customFields->sync($entity, PrFieldModule::Entity, $ownerId, $customFieldInput);

            return $entity;
        });
    }

    public function delete(int $id, int $ownerId): bool
    {
        return $this->entities->delete($this->entities->findOrFail($id, $ownerId));
    }
}
