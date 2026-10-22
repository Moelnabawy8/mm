<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Enums\Concerns;

/**
 * Adds a `value => label` map for backed enums, for populating select inputs.
 */
trait HasOptions
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
