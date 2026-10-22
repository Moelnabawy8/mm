<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\PublicRelations\Services\PrCustomFieldService;

/** @mixin \Modules\PublicRelations\Entities\PrEntity */
class PrEntityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'region' => $this->region?->value,
            'region_label' => $this->region?->label(),
            'contact_person' => $this->contact_person,
            'position' => $this->position,
            'phone' => $this->phone,
            'email' => $this->email,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'latest_interaction' => $this->whenLoaded(
                'latestInteractionLog',
                fn () => $this->latestInteractionLog ? new PrInteractionLogResource($this->latestInteractionLog) : null
            ),
            'custom_fields' => app(PrCustomFieldService::class)->get($this->resource),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
