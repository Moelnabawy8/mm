<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Implemented by models that can carry dynamic pr_fields/pr_field_values data.
 *
 * Deliberately does NOT redeclare getKey()/getMorphClass() — those are already
 * provided by Eloquent's Model, and redeclaring them here is a fatal PHP error
 * (Model's untyped declaration is incompatible with a typed interface
 * signature). Consumers that need both should type-hint `Fieldable&Model`.
 */
interface Fieldable
{
    public function fieldValues(): MorphMany;
}
