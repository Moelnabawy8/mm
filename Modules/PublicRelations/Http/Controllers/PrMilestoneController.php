<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\PublicRelations\Http\Requests\StorePrMilestoneRequest;
use Modules\PublicRelations\Http\Requests\UpdatePrMilestoneRequest;
use Modules\PublicRelations\Http\Resources\PrMilestoneResource;
use Modules\PublicRelations\Services\PrMilestoneService;

class PrMilestoneController extends Controller
{
    public function __construct(private readonly PrMilestoneService $milestones)
    {
    }

    /**
     * All milestones across entities, filterable by entity, status and date range.
     */
    public function index(Request $request): JsonResponse
    {
        if (! Auth::user()->can('manage pr entity')) {
            return $this->error(__('Permission Denied.'), 403);
        }

        $milestones = $this->milestones->list(
            $request->only(['entity_id', 'status', 'date_from', 'date_to']),
            $this->ownerId(),
            (int) ($request->input('per_page') ?? config('publicrelations.per_page', 15))
        );

        return $this->success([
            'milestones' => PrMilestoneResource::collection($milestones->items()),
            'pagination' => [
                'total' => $milestones->total(),
                'per_page' => $milestones->perPage(),
                'current_page' => $milestones->currentPage(),
                'last_page' => $milestones->lastPage(),
            ],
        ], __('Milestones retrieved.'));
    }

    public function store(StorePrMilestoneRequest $request, int $entity): JsonResponse
    {
        if (! Auth::user()->can('create pr entity')) {
            return $this->error(__('Permission Denied.'), 403);
        }

        $milestone = $this->milestones->create(
            $entity,
            $request->safe()->except('custom_fields'),
            $request->input('custom_fields', []),
            $this->ownerId()
        );

        return $this->success(new PrMilestoneResource($milestone), __('Milestone created.'), 201);
    }

    public function update(UpdatePrMilestoneRequest $request, int $milestone): JsonResponse
    {
        if (! Auth::user()->can('edit pr entity')) {
            return $this->error(__('Permission Denied.'), 403);
        }

        $updated = $this->milestones->update(
            $milestone,
            $request->safe()->except('custom_fields'),
            $request->input('custom_fields', []),
            $this->ownerId()
        );

        return $this->success(new PrMilestoneResource($updated), __('Milestone updated.'));
    }

    public function destroy(int $milestone): JsonResponse
    {
        if (! Auth::user()->can('delete pr entity')) {
            return $this->error(__('Permission Denied.'), 403);
        }

        $this->milestones->delete($milestone, $this->ownerId());

        return $this->success(null, __('Milestone deleted.'));
    }
}
