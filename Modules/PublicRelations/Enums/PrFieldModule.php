<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Enums;

use Modules\PublicRelations\Entities\PrEntity;
use Modules\PublicRelations\Entities\PrInteractionLog;
use Modules\PublicRelations\Entities\PrMilestone;
use Modules\PublicRelations\Enums\Concerns\HasOptions;

enum PrFieldModule: string
{
    use HasOptions;

    case Entity = 'entity';
    case InteractionLog = 'interaction_log';
    case Milestone = 'milestone';

    /**
     * The Eloquent model class that records of this module correspond to.
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Entity => PrEntity::class,
            self::InteractionLog => PrInteractionLog::class,
            self::Milestone => PrMilestone::class,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Entity => 'Entities',
            self::InteractionLog => 'Interaction Logs',
            self::Milestone => 'Milestones',
        };
    }
}
