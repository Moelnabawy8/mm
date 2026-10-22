<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Enums;

use Modules\PublicRelations\Enums\Concerns\HasOptions;

enum PrObjective: string
{
    use HasOptions;

    case Partnership = 'partnership';
    case Workshop = 'workshop';
    case Training = 'training';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Partnership => 'Partnership',
            self::Workshop => 'Workshop',
            self::Training => 'Training',
            self::Other => 'Other',
        };
    }
}
