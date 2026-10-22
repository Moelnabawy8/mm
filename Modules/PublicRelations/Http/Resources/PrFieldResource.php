<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Modules\PublicRelations\Entities\PrField */
class PrFieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'module' => $this->module->value,
            'label' => $this->label,
            'key' => $this->key,
            'type' => $this->type->value,
            'options' => $this->options,
            'is_required' => $this->is_required,
            'sort_order' => $this->sort_order,
        ];
    }
}
