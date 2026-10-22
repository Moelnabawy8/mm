<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\PublicRelations\Contracts\Attachable;
use Modules\PublicRelations\Entities\PrAttachment;

class PrAttachmentService
{
    public function store(UploadedFile $file, Attachable $attachable, int $uploadedBy): PrAttachment
    {
        $this->assertAllowed($file);

        $disk = config('publicrelations.attachments.disk', 'public');
        $path = $file->store(config('publicrelations.attachments.path', 'pr-attachments'), $disk);

        return $attachable->attachments()->create([
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => str_starts_with((string) $file->getMimeType(), 'image/') ? 'image' : 'document',
            'mime_type' => (string) $file->getMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $uploadedBy,
        ]);
    }

    public function delete(PrAttachment $attachment): bool
    {
        Storage::disk(config('publicrelations.attachments.disk', 'public'))->delete($attachment->file_path);

        return (bool) $attachment->delete();
    }

    private function assertAllowed(UploadedFile $file): void
    {
        $maxKb = (int) config('publicrelations.attachments.max_size_kb', 10240);
        $allowed = config('publicrelations.attachments.allowed_mimes', []);

        if ($file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages([
                'file' => ["The file may not be greater than {$maxKb} kilobytes."],
            ]);
        }

        if (! empty($allowed) && ! in_array(strtolower((string) $file->getClientOriginalExtension()), $allowed, true)) {
            throw ValidationException::withMessages([
                'file' => ['The file type is not supported.'],
            ]);
        }
    }
}
