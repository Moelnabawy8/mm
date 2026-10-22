<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Enums;

use Modules\PublicRelations\Enums\Concerns\HasOptions;

enum PrFieldType: string
{
    use HasOptions;

    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Date = 'date';
    case Select = 'select';
    case Checkbox = 'checkbox';

    /**
     * Whether this field type requires an `options` list to be configured.
     */
    public function requiresOptions(): bool
    {
        return in_array($this, [self::Select, self::Checkbox], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Textarea => 'Textarea',
            self::Number => 'Number',
            self::Date => 'Date',
            self::Select => 'Select (single choice)',
            self::Checkbox => 'Checkbox (multiple choice)',
        };
    }
}
