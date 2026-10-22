<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\PublicRelations\Enums\PrFieldModule;
use Modules\PublicRelations\Http\Requests\StorePrEntityRequest;
use Modules\PublicRelations\Http\Requests\UpdatePrEntityRequest;
use Modules\PublicRelations\Services\PrCustomFieldService;
use Modules\PublicRelations\Services\PrEntityService;
use Modules\PublicRelations\Services\PrFieldService;

class PrEntityWebController extends Controller
{
    public function __construct(
        private readonly PrEntityService $entities,
        private readonly PrFieldService $fields,
        private readonly PrCustomFieldService $customFields,
    ) {
    }

    /**
     * The main PR Sheet — filterable by entity type, status and date range.
     */
    public function index(Request $request): View
    {
        abort_unless(Auth::user()->can('manage pr entity'), 403, __('Permission Denied.'));

        $filters = $request->only(['type', 'region', 'status', 'date_from', 'date_to', 'search']);
        $entities = $this->entities->list($filters, $this->ownerId(), 20);

        if ($request->ajax()) {
            return view('publicrelations::entities._table', compact('entities'));
        }

        return view('publicrelations::entities.index', compact('entities', 'filters'));
    }

    public function create(): View
    {
        abort_unless(Auth::user()->can('create pr entity'), 403, __('Permission Denied.'));

        $prFields = $this->fields->listForModule(PrFieldModule::Entity, $this->ownerId());

        return view('publicrelations::entities.create', compact('prFields'));
    }

    public function store(StorePrEntityRequest $request): RedirectResponse
    {
        abort_unless(Auth::user()->can('create pr entity'), 403, __('Permission Denied.'));

        $this->entities->create(
            $request->safe()->except('custom_fields'),
            $request->input('custom_fields', []),
            $this->ownerId()
        );

        return redirect()->route('pr.index')->with('success', __('PR entity successfully created!'));
    }

    public function show(int $id): View
    {
        abort_unless(Auth::user()->can('manage pr entity'), 403, __('Permission Denied.'));

        $entity = $this->entities->find($id, $this->ownerId());
        $entity->load(['interactionLogs.previousAction', 'milestones', 'attachments']);
        $customFieldValues = $this->customFields->get($entity);

        return view('publicrelations::entities.show', compact('entity', 'customFieldValues'));
    }

    public function edit(int $id): View
    {
        abort_unless(Auth::user()->can('edit pr entity'), 403, __('Permission Denied.'));

        $entity = $this->entities->find($id, $this->ownerId());
        $prFields = $this->fields->listForModule(PrFieldModule::Entity, $this->ownerId());
        $customFieldValues = $this->customFields->get($entity);

        return view('publicrelations::entities.edit', compact('entity', 'prFields', 'customFieldValues'));
    }

    public function update(UpdatePrEntityRequest $request, int $id): RedirectResponse
    {
        abort_unless(Auth::user()->can('edit pr entity'), 403, __('Permission Denied.'));

        $this->entities->update(
            $id,
            $request->safe()->except('custom_fields'),
            $request->input('custom_fields', []),
            $this->ownerId()
        );

        return redirect()->route('pr.show', $id)->with('success', __('PR entity successfully updated!'));
    }

    public function destroy(int $id): RedirectResponse
    {
        abort_unless(Auth::user()->can('delete pr entity'), 403, __('Permission Denied.'));

        $this->entities->delete($id, $this->ownerId());

        return redirect()->route('pr.index')->with('success', __('PR entity successfully deleted!'));
    }
}
