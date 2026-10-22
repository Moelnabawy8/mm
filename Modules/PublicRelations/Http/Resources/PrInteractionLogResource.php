<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\PublicRelations\Services\PrCustomFieldService;

/** @mixin \Modules\PublicRelations\Entities\PrInteractionLog */
class PrInteractionLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pr_entity_id' => $this->pr_entity_id,
            'interaction_date' => $this->interaction_date?->toDateString(),
            'objective' => $this->objective->value,
            'current_action' => $this->current_action,
            'previous_action_id' => $this->previous_action_id,
            'next_action' => $this->next_action,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'custom_fields' => app(PrCustomFieldService::class)->get($this->resource),
            'attachments' => PrAttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
