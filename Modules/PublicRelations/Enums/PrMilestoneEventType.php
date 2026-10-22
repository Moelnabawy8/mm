<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Enums;

use Modules\PublicRelations\Enums\Concerns\HasOptions;

enum PrMilestoneEventType: string
{
    use HasOptions;

    case Workshop = 'workshop';
    case MouSigning = 'mou_signing';
    case ContractSigning = 'contract_signing';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Workshop => 'Workshop',
            self::MouSigning => 'MoU Signing',
            self::ContractSigning => 'Contract Signing',
            self::Other => 'Other',
        };
    }
}
