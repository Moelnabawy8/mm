{{ Form::open(['route' => 'pr.field.store', 'class' => 'needs-validation', 'novalidate']) }}
<div class="modal-body">
    <div class="row">
        <div class="col-6 form-group">
            {{ Form::label('module', __('Sheet'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('module', \Modules\PublicRelations\Enums\PrFieldModule::options(), request('module'), ['class' => 'form-control select2', 'required' => 'required']) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('type', __('Field Type'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('type', \Modules\PublicRelations\Enums\PrFieldType::options(), null, ['class' => 'form-control select2', 'required' => 'required']) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('label', __('Label'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::text('label', null, ['class' => 'form-control', 'required' => 'required', 'placeholder' => __('e.g. Commercial Registration No.')]) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('key', __('Key'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::text('key', null, ['class' => 'form-control', 'required' => 'required', 'placeholder' => __('e.g. commercial_registration_no')]) }}
            <small class="text-muted">{{ __('Lowercase letters, numbers and underscores only.') }}</small>
        </div>
        <div class="col-12 form-group">
            {{ Form::label('options', __('Options'), ['class' => 'form-label']) }}
            {{ Form::textarea('options', null, ['class' => 'form-control', 'rows' => 3, 'placeholder' => __("One option per line — only used for Select / Checkbox")]) }}
        </div>
        <div class="col-6 form-group">
            <div class="form-check mt-4">
                {{ Form::checkbox('is_required', 1, false, ['class' => 'form-check-input']) }}
                {{ Form::label('is_required', __('Required'), ['class' => 'form-check-label']) }}
            </div>
        </div>
        <div class="col-6 form-group">
            {{ Form::label('sort_order', __('Sort Order'), ['class' => 'form-label']) }}
            {{ Form::number('sort_order', 0, ['class' => 'form-control', 'min' => 0]) }}
        </div>
    </div>
</div>
<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn btn-secondary" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Create') }}" class="btn btn-primary">
</div>
{{ Form::close() }}
