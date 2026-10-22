<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Enums;

use Modules\PublicRelations\Enums\Concerns\HasOptions;

enum PrEntityType: string
{
    use HasOptions;

    case Company = 'company';
    case Factory = 'factory';
    case University = 'university';
    case Institution = 'institution';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Company => 'Company',
            self::Factory => 'Factory',
            self::University => 'University',
            self::Institution => 'Institution',
            self::Other => 'Other',
        };
    }
}
