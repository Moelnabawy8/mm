<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Enums;

use Modules\PublicRelations\Enums\Concerns\HasOptions;

enum PrRegion: string
{
    use HasOptions;

    case Egypt = 'egypt';
    case Libya = 'libya';
    case Jordan = 'jordan';
    case Syria = 'syria';

    public function label(): string
    {
        return match ($this) {
            self::Egypt => 'Egypt',
            self::Libya => 'Libya',
            self::Jordan => 'Jordan',
            self::Syria => 'Syria',
        };
    }
}
