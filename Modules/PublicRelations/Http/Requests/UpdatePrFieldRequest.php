<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\PublicRelations\Enums\PrFieldType;

class UpdatePrFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Allow `options` to arrive as a newline-separated string (plain HTML
     * textarea) as well as a genuine array (JSON API clients).
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('options'))) {
            $this->merge([
                'options' => array_values(array_filter(array_map('trim', explode("\n", $this->input('options'))))),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', Rule::enum(PrFieldType::class)],
            'options' => ['sometimes', 'array'],
            'is_required' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
