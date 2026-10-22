{{ Form::open(['url' => route('cost-center.store'), 'method' => 'POST', 'class' => 'needs-validation', 'novalidate' => true]) }}
<div class="modal-body">
    <div class="row">
        <div class="col-md-8">
            <div class="form-group">
                {{ Form::label('name', __('Name'), ['class' => 'form-label']) }}<x-required></x-required>
                {{ Form::text('name', null, ['class' => 'form-control', 'placeholder' => __('e.g. Marketing Department'), 'required' => 'required']) }}
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                {{ Form::label('code', __('Code'), ['class' => 'form-label']) }}
                {{ Form::text('code', null, ['class' => 'form-control', 'placeholder' => __('e.g. CC-001')]) }}
            </div>
        </div>
        <div class="col-md-12">
            <div class="form-group">
                {{ Form::label('description', __('Description'), ['class' => 'form-label']) }}
                {{ Form::textarea('description', null, ['class' => 'form-control', 'rows' => 3, 'placeholder' => __('Optional description...')]) }}
            </div>
        </div>
        <div class="col-md-12">
            <div class="form-group">
                {{ Form::label('status', __('Status'), ['class' => 'form-label']) }}
                {{ Form::select('status', ['active' => __('Active'), 'inactive' => __('Inactive')], 'active', ['class' => 'form-control']) }}
            </div>
        </div>
    </div>
</div>
<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn btn-secondary" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Create') }}" class="btn btn-primary">
</div>
{{ Form::close() }}
