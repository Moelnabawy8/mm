<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePrAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:' . config('publicrelations.attachments.max_size_kb', 10240)],
        ];
    }
}
