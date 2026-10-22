<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\PublicRelations\Enums\PrFieldModule;
use Modules\PublicRelations\Http\Requests\StorePrFieldRequest;
use Modules\PublicRelations\Http\Requests\UpdatePrFieldRequest;
use Modules\PublicRelations\Services\PrFieldService;

class PrFieldWebController extends Controller
{
    public function __construct(private readonly PrFieldService $fields)
    {
    }

    /**
     * The custom-field definitions for a given sheet (entity, interaction_log or milestone).
     */
    public function index(Request $request): View
    {
        abort_unless(Auth::user()->can('manage pr custom field'), 403, __('Permission Denied.'));

        $module = PrFieldModule::tryFrom((string) $request->query('module')) ?? PrFieldModule::Entity;
        $prFields = $this->fields->listForModule($module, $this->ownerId());

        return view('publicrelations::fields.index', compact('prFields', 'module'));
    }

    public function create(): View
    {
        abort_unless(Auth::user()->can('manage pr custom field'), 403, __('Permission Denied.'));

        return view('publicrelations::fields.create');
    }

    public function store(StorePrFieldRequest $request): RedirectResponse
    {
        abort_unless(Auth::user()->can('manage pr custom field'), 403, __('Permission Denied.'));

        $field = $this->fields->create($request->validated(), $this->ownerId());

        return redirect()->route('pr.field.index', ['module' => $field->module->value])
            ->with('success', __('Custom field successfully created!'));
    }

    public function edit(int $field): View
    {
        abort_unless(Auth::user()->can('manage pr custom field'), 403, __('Permission Denied.'));

        $prField = $this->fields->find($field, $this->ownerId());

        return view('publicrelations::fields.edit', compact('prField'));
    }

    public function update(UpdatePrFieldRequest $request, int $field): RedirectResponse
    {
        abort_unless(Auth::user()->can('manage pr custom field'), 403, __('Permission Denied.'));

        $updated = $this->fields->update($field, $request->validated(), $this->ownerId());

        return redirect()->route('pr.field.index', ['module' => $updated->module->value])
            ->with('success', __('Custom field successfully updated!'));
    }

    public function destroy(int $field): RedirectResponse
    {
        abort_unless(Auth::user()->can('manage pr custom field'), 403, __('Permission Denied.'));

        $prField = $this->fields->find($field, $this->ownerId());
        $module = $prField->module->value;
        $this->fields->delete($field, $this->ownerId());

        return redirect()->route('pr.field.index', ['module' => $module])
            ->with('success', __('Custom field successfully deleted!'));
    }
}
