<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\PublicRelations\Entities\PrFieldValue;

trait HasCustomFields
{
    public function fieldValues(): MorphMany
    {
        return $this->morphMany(PrFieldValue::class, 'fieldable');
    }
}
