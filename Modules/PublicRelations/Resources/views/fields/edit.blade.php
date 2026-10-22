{{ Form::model($prField, ['route' => ['pr.field.update', $prField->id], 'method' => 'PUT', 'class' => 'needs-validation', 'novalidate']) }}
<div class="modal-body">
    <div class="row">
        <div class="col-6 form-group">
            {{ Form::label('module', __('Sheet'), ['class' => 'form-label']) }}
            <input type="text" class="form-control" value="{{ $prField->module->label() }}" disabled>
        </div>
        <div class="col-6 form-group">
            {{ Form::label('type', __('Field Type'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('type', \Modules\PublicRelations\Enums\PrFieldType::options(), $prField->type->value, ['class' => 'form-control select2', 'required' => 'required']) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('label', __('Label'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::text('label', null, ['class' => 'form-control', 'required' => 'required']) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('key', __('Key'), ['class' => 'form-label']) }}
            <input type="text" class="form-control" value="{{ $prField->key }}" disabled>
        </div>
        <div class="col-12 form-group">
            {{ Form::label('options', __('Options'), ['class' => 'form-label']) }}
            {{ Form::textarea('options', implode("\n", $prField->options ?? []), ['class' => 'form-control', 'rows' => 3, 'placeholder' => __("One option per line — only used for Select / Checkbox")]) }}
        </div>
        <div class="col-6 form-group">
            <div class="form-check mt-4">
                {{ Form::checkbox('is_required', 1, $prField->is_required, ['class' => 'form-check-input']) }}
                {{ Form::label('is_required', __('Required'), ['class' => 'form-check-label']) }}
            </div>
        </div>
        <div class="col-6 form-group">
            {{ Form::label('sort_order', __('Sort Order'), ['class' => 'form-label']) }}
            {{ Form::number('sort_order', null, ['class' => 'form-control', 'min' => 0]) }}
        </div>
    </div>
</div>
<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn btn-secondary" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Update') }}" class="btn btn-primary">
</div>
{{ Form::close() }}
