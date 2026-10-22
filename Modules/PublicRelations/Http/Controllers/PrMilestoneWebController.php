<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\PublicRelations\Enums\PrFieldModule;
use Modules\PublicRelations\Http\Requests\StorePrMilestoneRequest;
use Modules\PublicRelations\Http\Requests\UpdatePrMilestoneRequest;
use Modules\PublicRelations\Services\PrCustomFieldService;
use Modules\PublicRelations\Services\PrEntityService;
use Modules\PublicRelations\Services\PrFieldService;
use Modules\PublicRelations\Services\PrMilestoneService;

class PrMilestoneWebController extends Controller
{
    public function __construct(
        private readonly PrMilestoneService $milestones,
        private readonly PrEntityService $entities,
        private readonly PrFieldService $fields,
        private readonly PrCustomFieldService $customFields,
    ) {
    }

    public function create(int $entity): View
    {
        abort_unless(Auth::user()->can('create pr entity'), 403, __('Permission Denied.'));

        $prEntity = $this->entities->find($entity, $this->ownerId());
        $prFields = $this->fields->listForModule(PrFieldModule::Milestone, $this->ownerId());

        return view('publicrelations::milestones.create', compact('prEntity', 'prFields'));
    }

    public function store(StorePrMilestoneRequest $request, int $entity): RedirectResponse
    {
        abort_unless(Auth::user()->can('create pr entity'), 403, __('Permission Denied.'));

        $this->milestones->create(
            $entity,
            $request->safe()->except('custom_fields'),
            $request->input('custom_fields', []),
            $this->ownerId()
        );

        return redirect()->route('pr.show', $entity)->with('success', __('Milestone successfully created!'));
    }

    public function edit(int $milestone): View
    {
        abort_unless(Auth::user()->can('edit pr entity'), 403, __('Permission Denied.'));

        $prMilestone = $this->milestones->find($milestone, $this->ownerId());
        $prFields = $this->fields->listForModule(PrFieldModule::Milestone, $this->ownerId());
        $customFieldValues = $this->customFields->get($prMilestone);

        return view('publicrelations::milestones.edit', compact('prMilestone', 'prFields', 'customFieldValues'));
    }

    public function update(UpdatePrMilestoneRequest $request, int $milestone): RedirectResponse
    {
        abort_unless(Auth::user()->can('edit pr entity'), 403, __('Permission Denied.'));

        $updated = $this->milestones->update(
            $milestone,
            $request->safe()->except('custom_fields'),
            $request->input('custom_fields', []),
            $this->ownerId()
        );

        return redirect()->route('pr.show', $updated->pr_entity_id)->with('success', __('Milestone successfully updated!'));
    }

    public function destroy(int $milestone): RedirectResponse
    {
        abort_unless(Auth::user()->can('delete pr entity'), 403, __('Permission Denied.'));

        $prMilestone = $this->milestones->find($milestone, $this->ownerId());
        $entityId = $prMilestone->pr_entity_id;
        $this->milestones->delete($milestone, $this->ownerId());

        return redirect()->route('pr.show', $entityId)->with('success', __('Milestone successfully deleted!'));
    }
}
