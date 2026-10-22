{{ Form::open(['url' => route('shift.store'), 'method' => 'POST', 'class' => 'needs-validation', 'novalidate' => true]) }}
<div class="modal-body">
    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                {{ Form::label('name', __('Shift Name'), ['class' => 'form-label']) }}<x-required></x-required>
                {{ Form::text('name', null, ['class' => 'form-control', 'placeholder' => __('e.g. Morning Shift'), 'required' => 'required']) }}
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {{ Form::label('start_time', __('Start Time'), ['class' => 'form-label']) }}<x-required></x-required>
                {{ Form::time('start_time', null, ['class' => 'form-control', 'required' => 'required']) }}
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {{ Form::label('end_time', __('End Time'), ['class' => 'form-label']) }}<x-required></x-required>
                {{ Form::time('end_time', null, ['class' => 'form-control', 'required' => 'required']) }}
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {{ Form::label('break_duration', __('Break Duration (minutes)'), ['class' => 'form-label']) }}
                {{ Form::number('break_duration', 0, ['class' => 'form-control', 'min' => 0, 'max' => 240]) }}
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {{ Form::label('status', __('Status'), ['class' => 'form-label']) }}
                {{ Form::select('status', ['active' => __('Active'), 'inactive' => __('Inactive')], 'active', ['class' => 'form-control']) }}
            </div>
        </div>
        <div class="col-md-12">
            <div class="form-group">
                {{ Form::label('working_days', __('Working Days'), ['class' => 'form-label']) }}
                <div class="d-flex flex-wrap gap-2 mt-1">
                    @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day)
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="working_days[]"
                               value="{{ $day }}" id="day_{{ $day }}"
                               @if(!in_array($day, ['Sat','Sun'])) checked @endif>
                        <label class="form-check-label" for="day_{{ $day }}">{{ __($day) }}</label>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn btn-secondary" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Create') }}" class="btn btn-primary">
</div>
{{ Form::close() }}
