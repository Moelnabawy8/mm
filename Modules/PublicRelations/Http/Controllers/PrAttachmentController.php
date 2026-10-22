<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Modules\PublicRelations\Contracts\Attachable;
use Modules\PublicRelations\Entities\PrAttachment;
use Modules\PublicRelations\Http\Requests\StorePrAttachmentRequest;
use Modules\PublicRelations\Http\Resources\PrAttachmentResource;
use Modules\PublicRelations\Repositories\Contracts\PrEntityRepositoryInterface;
use Modules\PublicRelations\Repositories\Contracts\PrInteractionLogRepositoryInterface;
use Modules\PublicRelations\Repositories\Contracts\PrMilestoneRepositoryInterface;
use Modules\PublicRelations\Services\PrAttachmentService;

class PrAttachmentController extends Controller
{
    public function __construct(
        private readonly PrAttachmentService $attachments,
        private readonly PrEntityRepositoryInterface $entities,
        private readonly PrInteractionLogRepositoryInterface $logs,
        private readonly PrMilestoneRepositoryInterface $milestones,
    ) {
    }

    public function storeForEntity(StorePrAttachmentRequest $request, int $entity): JsonResponse
    {
        return $this->upload($request, $this->entities->findOrFail($entity, $this->ownerId()));
    }

    public function storeForInteractionLog(StorePrAttachmentRequest $request, int $log): JsonResponse
    {
        return $this->upload($request, $this->logs->findOrFail($log, $this->ownerId()));
    }

    public function storeForMilestone(StorePrAttachmentRequest $request, int $milestone): JsonResponse
    {
        return $this->upload($request, $this->milestones->findOrFail($milestone, $this->ownerId()));
    }

    public function destroy(PrAttachment $attachment): JsonResponse
    {
        if (! Auth::user()->can('edit pr entity')) {
            return $this->error(__('Permission Denied.'), 403);
        }

        if ((int) ($attachment->attachable?->created_by) !== $this->ownerId()) {
            return $this->error(__('Permission Denied.'), 403);
        }

        $this->attachments->delete($attachment);

        return $this->success(null, __('Attachment deleted.'));
    }

    private function upload(StorePrAttachmentRequest $request, Attachable $attachable): JsonResponse
    {
        if (! Auth::user()->can('edit pr entity')) {
            return $this->error(__('Permission Denied.'), 403);
        }

        $attachment = $this->attachments->store($request->file('file'), $attachable, (int) Auth::id());

        return $this->success(new PrAttachmentResource($attachment), __('Attachment uploaded.'), 201);
    }
}
