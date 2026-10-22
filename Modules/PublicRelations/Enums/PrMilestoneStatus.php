<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Enums;

use Modules\PublicRelations\Enums\Concerns\HasOptions;

enum PrMilestoneStatus: string
{
    use HasOptions;

    case Upcoming = 'upcoming';
    case Completed = 'completed';
    case Postponed = 'postponed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Upcoming => 'Upcoming',
            self::Completed => 'Completed',
            self::Postponed => 'Postponed',
            self::Cancelled => 'Cancelled',
        };
    }
}
