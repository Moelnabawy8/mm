<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Services;

use Illuminate\Support\Collection;
use Modules\PublicRelations\Entities\PrField;
use Modules\PublicRelations\Enums\PrFieldModule;
use Modules\PublicRelations\Repositories\Contracts\PrFieldRepositoryInterface;

class PrFieldService
{
    public function __construct(private readonly PrFieldRepositoryInterface $fields)
    {
    }

    public function listForModule(PrFieldModule $module, int $ownerId): Collection
    {
        return $this->fields->listForModule($module, $ownerId);
    }

    public function find(int $id, int $ownerId): PrField
    {
        return $this->fields->findOrFail($id, $ownerId);
    }

    public function create(array $data, int $ownerId): PrField
    {
        return $this->fields->create([...$data, 'created_by' => $ownerId]);
    }

    public function update(int $id, array $data, int $ownerId): PrField
    {
        return $this->fields->update($this->fields->findOrFail($id, $ownerId), $data);
    }

    public function delete(int $id, int $ownerId): bool
    {
        return $this->fields->delete($this->fields->findOrFail($id, $ownerId));
    }
}
