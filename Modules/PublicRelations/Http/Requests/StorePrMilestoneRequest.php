<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\PublicRelations\Enums\PrMilestoneEventType;
use Modules\PublicRelations\Enums\PrMilestoneStatus;

class StorePrMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_type' => ['required', Rule::enum(PrMilestoneEventType::class)],
            'event_date' => ['required', 'date'],
            'status' => ['sometimes', Rule::enum(PrMilestoneStatus::class)],
            'custom_fields' => ['sometimes', 'array'],
        ];
    }
}
