{{ Form::model($prMilestone, ['route' => ['pr.milestone.update', $prMilestone->id], 'method' => 'PUT', 'class' => 'needs-validation', 'novalidate']) }}
<div class="modal-body">
    <div class="row">
        <div class="col-6 form-group">
            {{ Form::label('event_type', __('Event Type'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('event_type', \Modules\PublicRelations\Enums\PrMilestoneEventType::options(), $prMilestone->event_type->value, ['class' => 'form-control select2', 'required' => 'required']) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('event_date', __('Event Date'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::date('event_date', null, ['class' => 'form-control', 'required' => 'required']) }}
        </div>
        <div class="col-12 form-group">
            {{ Form::label('status', __('Status'), ['class' => 'form-label']) }}
            {{ Form::select('status', \Modules\PublicRelations\Enums\PrMilestoneStatus::options(), $prMilestone->status->value, ['class' => 'form-control select2']) }}
        </div>
    </div>

    @include('publicrelations::fields._form_builder', ['prFields' => $prFields, 'customFieldValues' => $customFieldValues])
</div>
<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn btn-secondary" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Update') }}" class="btn btn-primary">
</div>
{{ Form::close() }}
