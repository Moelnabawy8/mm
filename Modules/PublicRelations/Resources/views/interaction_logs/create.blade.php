{{ Form::open(['route' => ['pr.logs.store', $prEntity->id], 'class' => 'needs-validation', 'novalidate']) }}
<div class="modal-body">
    <div class="row">
        <div class="col-6 form-group">
            {{ Form::label('interaction_date', __('Interaction Date'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::date('interaction_date', now()->toDateString(), ['class' => 'form-control', 'required' => 'required']) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('objective', __('Objective'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('objective', \Modules\PublicRelations\Enums\PrObjective::options(), null, ['class' => 'form-control select2', 'required' => 'required', 'placeholder' => __('Select Objective')]) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('current_action', __('Current Action'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::text('current_action', null, ['class' => 'form-control', 'required' => 'required', 'placeholder' => __('e.g. Intro Call, Zoom Meeting, Site Visit')]) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('next_action', __('Next Action'), ['class' => 'form-label']) }}
            {{ Form::text('next_action', null, ['class' => 'form-control', 'placeholder' => __('e.g. Send Proposal, Schedule Workshop')]) }}
        </div>
        <div class="col-12 form-group">
            {{ Form::label('notes', __('Notes'), ['class' => 'form-label']) }}
            {{ Form::textarea('notes', null, ['class' => 'form-control', 'rows' => 3]) }}
        </div>
    </div>

    @include('publicrelations::fields._form_builder')
</div>
<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn btn-secondary" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Save') }}" class="btn btn-primary">
</div>
{{ Form::close() }}
