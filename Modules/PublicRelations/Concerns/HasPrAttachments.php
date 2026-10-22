<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\PublicRelations\Entities\PrAttachment;

trait HasPrAttachments
{
    public function attachments(): MorphMany
    {
        return $this->morphMany(PrAttachment::class, 'attachable');
    }
}
