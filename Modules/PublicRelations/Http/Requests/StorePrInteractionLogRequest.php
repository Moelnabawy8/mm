<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\PublicRelations\Enums\PrObjective;

class StorePrInteractionLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'interaction_date' => ['required', 'date'],
            'objective' => ['required', Rule::enum(PrObjective::class)],
            'current_action' => ['required', 'string', 'max:255'],
            'next_action' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'custom_fields' => ['sometimes', 'array'],
        ];
    }
}
