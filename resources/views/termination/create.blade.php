{{ Form::open(['url' => 'termination', 'method' => 'post', 'class' => 'needs-validation', 'novalidate']) }}
<div class="modal-body">
    {{-- start for ai module --}}
    @php
        $plan = \App\Models\Utility::getChatGPTSettings();
    @endphp
    @if ($plan->chatgpt == 1)
        <div class="text-end">
            <a href="#" data-size="md" class="btn  btn-primary btn-icon btn-sm" data-ajax-popup-over="true"
                data-url="{{ route('generate', ['termination']) }}" data-bs-placement="top"
                data-title="{{ __('Generate content with AI') }}">
                <i class="fas fa-robot"></i> <span>{{ __('Generate with AI') }}</span>
            </a>
        </div>
    @endif
    {{-- end for ai module --}}
    <div class="row">
        <div class="form-group col-lg-6 col-md-6">
            {{ Form::label('employee_id', __('Employee'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('employee_id', $employees, isset($employee_id) ? $employee_id : null, ['class' => 'form-control select', 'required' => 'required', 'id' => 'employee_id']) }}
            <div class="text-xs mt-1">
                {{ __('Create employee here.') }} <a
                    href="{{ route('employee.index') }}"><b>{{ __('Create employee') }}</b></a>
            </div>
            <div id="clearance-warning" class="alert alert-warning mt-2" style="display: none;">
                <i class="ti ti-alert-circle"></i> {{ __('This employee has not been cleared yet.') }}
            </div>
        </div>

        <script>
            $(document).ready(function() {
                var clearedEmployees = @json($cleared_employees ?? []);

                function checkClearance(employeeId) {
                    if (employeeId && !clearedEmployees.includes(parseInt(employeeId))) {
                        $('#clearance-warning').show();
                    } else {
                        $('#clearance-warning').hide();
                    }
                }

                // Check on load if employee is selected
                checkClearance($('#employee_id').val());

                // Check on change
                $('#employee_id').on('change', function() {
                    checkClearance($(this).val());
                });
            });
        </script>
        <div class="form-group col-lg-6 col-md-6">
            {{ Form::label('termination_type', __('Termination Type'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('termination_type', $terminationtypes, null, ['class' => 'form-control select', 'required' => 'required']) }}
            <div class="text-xs mt-1">
                {{ __('Create termination type here.') }} <a
                    href="{{ route('terminationtype.index') }}"><b>{{ __('Create termination type') }}</b></a>
            </div>
        </div>
        <div class="form-group col-lg-6 col-md-6">
            {{ Form::label('notice_date', __('Notice Date'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::date('notice_date', null, ['class' => 'form-control', 'required' => 'required']) }}
        </div>
        <div class="form-group col-lg-6 col-md-6">
            {{ Form::label('termination_date', __('Termination Date'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::date('termination_date', null, ['class' => 'form-control', 'required' => 'required']) }}
        </div>
        <div class="form-group  col-lg-12">
            {{ Form::label('description', __('Description'), ['class' => 'form-label']) }}
            {{ Form::textarea('description', null, ['class' => 'form-control', 'placeholder' => __('Enter Description')]) }}
        </div>
    </div>
</div>
<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn  btn-secondary" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Create') }}" class="btn  btn-primary">
</div>
{{ Form::close() }}
