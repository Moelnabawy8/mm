<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Modules\PublicRelations\Entities\PrAttachment;
use Modules\PublicRelations\Services\PrAttachmentService;
use Modules\PublicRelations\Services\PrEntityService;

class PrAttachmentWebController extends Controller
{
    public function __construct(
        private readonly PrAttachmentService $attachments,
        private readonly PrEntityService $entities,
    ) {
    }

    public function upload(Request $request, int $entity)
    {
        if (! Auth::user()->can('edit pr entity')) {
            return response()->json(['is_success' => false, 'error' => __('Permission Denied.')], 403);
        }

        $request->validate([
            'file' => 'required|file|max:' . config('publicrelations.attachments.max_size_kb', 10240),
        ]);

        $prEntity = $this->entities->find($entity, $this->ownerId());
        $attachment = $this->attachments->store($request->file('file'), $prEntity, (int) Auth::id());

        return response()->json([
            'is_success' => true,
            'success_msg' => __('File uploaded successfully'),
            'attachment' => $attachment,
        ]);
    }

    public function download(int $entity, int $attachment): Response
    {
        $prEntity = $this->entities->find($entity, $this->ownerId());
        $prAttachment = $prEntity->attachments()->findOrFail($attachment);

        $disk = config('publicrelations.attachments.disk', 'public');
        abort_unless(Storage::disk($disk)->exists($prAttachment->file_path), 404, __('File not found.'));

        return Storage::disk($disk)->download($prAttachment->file_path, $prAttachment->file_name);
    }

    public function serve(int $entity, int $attachment): Response
    {
        $prEntity = $this->entities->find($entity, $this->ownerId());
        $prAttachment = $prEntity->attachments()->findOrFail($attachment);

        $disk = config('publicrelations.attachments.disk', 'public');
        abort_unless(Storage::disk($disk)->exists($prAttachment->file_path), 404, __('File not found.'));

        return Storage::disk($disk)->response($prAttachment->file_path, $prAttachment->file_name, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function destroy(int $entity, int $attachment): JsonResponse
    {
        if (! Auth::user()->can('edit pr entity')) {
            return response()->json(['is_success' => false, 'error' => __('Permission Denied.')], 403);
        }

        $prEntity = $this->entities->find($entity, $this->ownerId());
        /** @var PrAttachment $prAttachment */
        $prAttachment = $prEntity->attachments()->findOrFail($attachment);

        $this->attachments->delete($prAttachment);

        return response()->json(['is_success' => true, 'success_msg' => __('File deleted successfully')]);
    }
}
