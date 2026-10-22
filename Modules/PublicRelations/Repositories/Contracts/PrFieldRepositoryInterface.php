<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\PublicRelations\Entities\PrField;
use Modules\PublicRelations\Enums\PrFieldModule;

interface PrFieldRepositoryInterface
{
    public function listForModule(PrFieldModule $module, int $ownerId): Collection;

    public function findOrFail(int $id, int $ownerId): PrField;

    public function create(array $data): PrField;

    public function update(PrField $field, array $data): PrField;

    public function delete(PrField $field): bool;
}
