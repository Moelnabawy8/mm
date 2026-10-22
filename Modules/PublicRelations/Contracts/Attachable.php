<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Implemented by models that can carry pr_attachments.
 *
 * Deliberately does NOT redeclare getKey()/getMorphClass() — those are already
 * provided by Eloquent's Model, and redeclaring them here is a fatal PHP error
 * (Model's untyped declaration is incompatible with a typed interface
 * signature). Consumers that need both should type-hint `Attachable&Model`.
 */
interface Attachable
{
    public function attachments(): MorphMany;
}
