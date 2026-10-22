<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\PublicRelations\Enums\PrEntityStatus;
use Modules\PublicRelations\Enums\PrEntityType;
use Modules\PublicRelations\Enums\PrRegion;

class UpdatePrEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', Rule::enum(PrEntityType::class)],
            'region' => ['nullable', Rule::enum(PrRegion::class)],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['sometimes', Rule::enum(PrEntityStatus::class)],
            'custom_fields' => ['sometimes', 'array'],
        ];
    }
}
