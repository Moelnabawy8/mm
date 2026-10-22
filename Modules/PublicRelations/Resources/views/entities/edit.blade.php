{{ Form::model($entity, ['route' => ['pr.update', $entity->id], 'method' => 'PUT', 'class' => 'needs-validation', 'novalidate']) }}
<div class="modal-body">
    <div class="row">
        <div class="col-6 form-group">
            {{ Form::label('name', __('Name'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::text('name', null, ['class' => 'form-control', 'required' => 'required']) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('type', __('Type'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('type', \Modules\PublicRelations\Enums\PrEntityType::options(), $entity->type->value, ['class' => 'form-control select2', 'required' => 'required']) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('region', __('Region'), ['class' => 'form-label']) }}
            {{ Form::select('region', \Modules\PublicRelations\Enums\PrRegion::options(), $entity->region?->value, ['class' => 'form-control select2', 'placeholder' => __('Select Region')]) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('contact_person', __('Contact Person'), ['class' => 'form-label']) }}
            {{ Form::text('contact_person', null, ['class' => 'form-control']) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('position', __('Position'), ['class' => 'form-label']) }}
            {{ Form::text('position', null, ['class' => 'form-control']) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('phone', __('Phone'), ['class' => 'form-label']) }}
            {{ Form::text('phone', null, ['class' => 'form-control']) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('email', __('Email'), ['class' => 'form-label']) }}
            {{ Form::email('email', null, ['class' => 'form-control']) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('status', __('Status'), ['class' => 'form-label']) }}
            {{ Form::select('status', \Modules\PublicRelations\Enums\PrEntityStatus::options(), $entity->status->value, ['class' => 'form-control select2']) }}
        </div>
    </div>

    @include('publicrelations::fields._form_builder', ['prFields' => $prFields, 'customFieldValues' => $customFieldValues])
</div>
<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn btn-secondary" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Update') }}" class="btn btn-primary">
</div>
{{ Form::close() }}
