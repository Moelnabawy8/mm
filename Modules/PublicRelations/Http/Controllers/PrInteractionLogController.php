<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\PublicRelations\Http\Requests\StorePrInteractionLogRequest;
use Modules\PublicRelations\Http\Requests\UpdatePrInteractionLogRequest;
use Modules\PublicRelations\Http\Resources\PrInteractionLogResource;
use Modules\PublicRelations\Services\PrInteractionLogService;

class PrInteractionLogController extends Controller
{
    public function __construct(private readonly PrInteractionLogService $logs)
    {
    }

    /**
     * The pipeline rows for a single PR entity, newest first.
     */
    public function index(Request $request, int $entity): JsonResponse
    {
        if (! Auth::user()->can('manage pr entity')) {
            return $this->error(__('Permission Denied.'), 403);
        }

        $logs = $this->logs->listForEntity(
            $entity,
            $request->only(['date_from', 'date_to', 'objective']),
            $this->ownerId(),
            (int) ($request->input('per_page') ?? config('publicrelations.per_page', 15))
        );

        return $this->success([
            'logs' => PrInteractionLogResource::collection($logs->items()),
            'pagination' => [
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
            ],
        ], __('Interaction logs retrieved.'));
    }

    /**
     * Record a new interaction. The previous action is linked automatically
     * from the last logged entry for this entity.
     */
    public function store(StorePrInteractionLogRequest $request, int $entity): JsonResponse
    {
        if (! Auth::user()->can('create pr entity')) {
            return $this->error(__('Permission Denied.'), 403);
        }

        $log = $this->logs->create(
            $entity,
            $request->safe()->except('custom_fields'),
            $request->input('custom_fields', []),
            $this->ownerId()
        );

        return $this->success(new PrInteractionLogResource($log), __('Interaction log created.'), 201);
    }

    public function update(UpdatePrInteractionLogRequest $request, int $entity, int $log): JsonResponse
    {
        if (! Auth::user()->can('edit pr entity')) {
            return $this->error(__('Permission Denied.'), 403);
        }

        $updated = $this->logs->update(
            $log,
            $request->safe()->except('custom_fields'),
            $request->input('custom_fields', []),
            $this->ownerId()
        );

        return $this->success(new PrInteractionLogResource($updated), __('Interaction log updated.'));
    }

    public function destroy(int $entity, int $log): JsonResponse
    {
        if (! Auth::user()->can('delete pr entity')) {
            return $this->error(__('Permission Denied.'), 403);
        }

        $this->logs->delete($log, $this->ownerId());

        return $this->success(null, __('Interaction log deleted.'));
    }
}
