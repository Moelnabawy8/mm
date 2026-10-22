<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\PublicRelations\Http\Requests\StorePrEntityRequest;
use Modules\PublicRelations\Http\Requests\UpdatePrEntityRequest;
use Modules\PublicRelations\Http\Resources\PrEntityResource;
use Modules\PublicRelations\Services\PrEntityService;

class PrEntityController extends Controller
{
    public function __construct(private readonly PrEntityService $entities)
    {
    }

    /**
     * The main PR Sheet — filterable by entity type, status and date range.
     */
    public function index(Request $request): JsonResponse
    {
        if (! Auth::user()->can('manage pr entity')) {
            return $this->error(__('Permission Denied.'), 403);
        }

        $entities = $this->entities->list(
            $request->only(['type', 'region', 'status', 'date_from', 'date_to', 'search']),
            $this->ownerId(),
            (int) ($request->input('per_page') ?? config('publicrelations.per_page', 15))
        );

        return $this->success([
            'entities' => PrEntityResource::collection($entities->items()),
            'pagination' => [
                'total' => $entities->total(),
                'per_page' => $entities->perPage(),
                'current_page' => $entities->currentPage(),
                'last_page' => $entities->lastPage(),
            ],
        ], __('PR entities retrieved.'));
    }

    public function store(StorePrEntityRequest $request): JsonResponse
    {
        if (! Auth::user()->can('create pr entity')) {
            return $this->error(__('Permission Denied.'), 403);
        }

        $entity = $this->entities->create(
            $request->safe()->except('custom_fields'),
            $request->input('custom_fields', []),
            $this->ownerId()
        );

        return $this->success(new PrEntityResource($entity), __('PR entity created.'), 201);
    }

    public function show(int $id): JsonResponse
    {
        if (! Auth::user()->can('manage pr entity')) {
            return $this->error(__('Permission Denied.'), 403);
        }

        $entity = $this->entities->find($id, $this->ownerId());
        $entity->load(['latestInteractionLog', 'milestones', 'attachments']);

        return $this->success(new PrEntityResource($entity), __('PR entity retrieved.'));
    }

    public function update(UpdatePrEntityRequest $request, int $id): JsonResponse
    {
        if (! Auth::user()->can('edit pr entity')) {
            return $this->error(__('Permission Denied.'), 403);
        }

        $entity = $this->entities->update(
            $id,
            $request->safe()->except('custom_fields'),
            $request->input('custom_fields', []),
            $this->ownerId()
        );

        return $this->success(new PrEntityResource($entity), __('PR entity updated.'));
    }

    public function destroy(int $id): JsonResponse
    {
        if (! Auth::user()->can('delete pr entity')) {
            return $this->error(__('Permission Denied.'), 403);
        }

        $this->entities->delete($id, $this->ownerId());

        return $this->success(null, __('PR entity deleted.'));
    }
}
