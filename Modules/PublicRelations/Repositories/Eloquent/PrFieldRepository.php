<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Repositories\Eloquent;

use Illuminate\Support\Collection;
use Modules\PublicRelations\Entities\PrField;
use Modules\PublicRelations\Enums\PrFieldModule;
use Modules\PublicRelations\Repositories\Contracts\PrFieldRepositoryInterface;

class PrFieldRepository implements PrFieldRepositoryInterface
{
    public function listForModule(PrFieldModule $module, int $ownerId): Collection
    {
        return PrField::query()
            ->forModule($module)
            ->ownedBy($ownerId)
            ->orderBy('sort_order')
            ->get();
    }

    public function findOrFail(int $id, int $ownerId): PrField
    {
        return PrField::query()->ownedBy($ownerId)->findOrFail($id);
    }

    public function create(array $data): PrField
    {
        return PrField::create($data);
    }

    public function update(PrField $field, array $data): PrField
    {
        $field->update($data);

        return $field->refresh();
    }

    public function delete(PrField $field): bool
    {
        return (bool) $field->delete();
    }
}
