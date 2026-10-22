<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\PublicRelations\Enums\PrFieldModule;
use Modules\PublicRelations\Enums\PrFieldType;

class StorePrFieldRequest extends FormRequest
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
            'module' => ['required', Rule::enum(PrFieldModule::class)],
            'label' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
            'type' => ['required', Rule::enum(PrFieldType::class)],
            'options' => ['required_if:type,select,checkbox', 'array'],
            'is_required' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
