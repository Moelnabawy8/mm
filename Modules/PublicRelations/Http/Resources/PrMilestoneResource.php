<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\PublicRelations\Services\PrCustomFieldService;

/** @mixin \Modules\PublicRelations\Entities\PrMilestone */
class PrMilestoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pr_entity_id' => $this->pr_entity_id,
            'event_type' => $this->event_type->value,
            'event_date' => $this->event_date?->toDateString(),
            'status' => $this->status->value,
            'custom_fields' => app(PrCustomFieldService::class)->get($this->resource),
            'attachments' => PrAttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
