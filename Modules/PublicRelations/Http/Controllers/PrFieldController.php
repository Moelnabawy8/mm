<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\PublicRelations\Enums\PrFieldModule;
use Modules\PublicRelations\Http\Requests\StorePrFieldRequest;
use Modules\PublicRelations\Http\Requests\UpdatePrFieldRequest;
use Modules\PublicRelations\Http\Resources\PrFieldResource;
use Modules\PublicRelations\Services\PrFieldService;

class PrFieldController extends Controller
{
    public function __construct(private readonly PrFieldService $fields)
    {
    }

    /**
     * The dynamic custom-field definitions configured for a given sheet
     * (entity, interaction_log or milestone).
     */
    public function index(Request $request): JsonResponse
    {
        if (! Auth::user()->can('manage pr custom field')) {
            return $this->error(__('Permission Denied.'), 403);
        }

        $module = PrFieldModule::tryFrom((string) $request->query('module')) ?? PrFieldModule::Entity;

        return $this->success(
            PrFieldResource::collection($this->fields->listForModule($module, $this->ownerId())),
            __('Custom fields retrieved.')
        );
    }

    public function store(StorePrFieldRequest $request): JsonResponse
    {
        if (! Auth::user()->can('manage pr custom field')) {
            return $this->error(__('Permission Denied.'), 403);
        }

        $field = $this->fields->create($request->validated(), $this->ownerId());

        return $this->success(new PrFieldResource($field), __('Custom field created.'), 201);
    }

    public function update(UpdatePrFieldRequest $request, int $field): JsonResponse
    {
        if (! Auth::user()->can('manage pr custom field')) {
            return $this->error(__('Permission Denied.'), 403);
        }

        $updated = $this->fields->update($field, $request->validated(), $this->ownerId());

        return $this->success(new PrFieldResource($updated), __('Custom field updated.'));
    }

    public function destroy(int $field): JsonResponse
    {
        if (! Auth::user()->can('manage pr custom field')) {
            return $this->error(__('Permission Denied.'), 403);
        }

        $this->fields->delete($field, $this->ownerId());

        return $this->success(null, __('Custom field deleted.'));
    }
}
