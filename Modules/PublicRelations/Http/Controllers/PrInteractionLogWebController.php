<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\PublicRelations\Enums\PrFieldModule;
use Modules\PublicRelations\Http\Requests\StorePrInteractionLogRequest;
use Modules\PublicRelations\Http\Requests\UpdatePrInteractionLogRequest;
use Modules\PublicRelations\Services\PrCustomFieldService;
use Modules\PublicRelations\Services\PrEntityService;
use Modules\PublicRelations\Services\PrFieldService;
use Modules\PublicRelations\Services\PrInteractionLogService;

class PrInteractionLogWebController extends Controller
{
    public function __construct(
        private readonly PrInteractionLogService $logs,
        private readonly PrEntityService $entities,
        private readonly PrFieldService $fields,
        private readonly PrCustomFieldService $customFields,
    ) {
    }

    public function create(int $entity): View
    {
        abort_unless(Auth::user()->can('create pr entity'), 403, __('Permission Denied.'));

        $prEntity = $this->entities->find($entity, $this->ownerId());
        $prFields = $this->fields->listForModule(PrFieldModule::InteractionLog, $this->ownerId());

        return view('publicrelations::interaction_logs.create', compact('prEntity', 'prFields'));
    }

    /**
     * Record a new interaction. The previous action is linked automatically
     * from the last logged entry for this entity.
     */
    public function store(StorePrInteractionLogRequest $request, int $entity): RedirectResponse
    {
        abort_unless(Auth::user()->can('create pr entity'), 403, __('Permission Denied.'));

        $this->logs->create(
            $entity,
            $request->safe()->except('custom_fields'),
            $request->input('custom_fields', []),
            $this->ownerId()
        );

        return redirect()->route('pr.show', $entity)->with('success', __('Interaction log successfully created!'));
    }

    public function edit(int $entity, int $log): View
    {
        abort_unless(Auth::user()->can('edit pr entity'), 403, __('Permission Denied.'));

        $prEntity = $this->entities->find($entity, $this->ownerId());
        $interactionLog = $this->logs->find($log, $this->ownerId());
        $prFields = $this->fields->listForModule(PrFieldModule::InteractionLog, $this->ownerId());
        $customFieldValues = $this->customFields->get($interactionLog);

        return view('publicrelations::interaction_logs.edit', compact('prEntity', 'interactionLog', 'prFields', 'customFieldValues'));
    }

    public function update(UpdatePrInteractionLogRequest $request, int $entity, int $log): RedirectResponse
    {
        abort_unless(Auth::user()->can('edit pr entity'), 403, __('Permission Denied.'));

        $this->logs->update(
            $log,
            $request->safe()->except('custom_fields'),
            $request->input('custom_fields', []),
            $this->ownerId()
        );

        return redirect()->route('pr.show', $entity)->with('success', __('Interaction log successfully updated!'));
    }

    public function destroy(int $entity, int $log): RedirectResponse
    {
        abort_unless(Auth::user()->can('delete pr entity'), 403, __('Permission Denied.'));

        $this->logs->delete($log, $this->ownerId());

        return redirect()->route('pr.show', $entity)->with('success', __('Interaction log successfully deleted!'));
    }
}
