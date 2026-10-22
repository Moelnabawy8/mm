<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Enums;

use Modules\PublicRelations\Enums\Concerns\HasOptions;

enum PrEntityStatus: string
{
    use HasOptions;

    case Prospect = 'prospect';
    case Active = 'active';
    case Partner = 'partner';
    case OnHold = 'on_hold';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Prospect => 'Prospect',
            self::Active => 'Active',
            self::Partner => 'Partner',
            self::OnHold => 'On Hold',
            self::Inactive => 'Inactive',
        };
    }
}
