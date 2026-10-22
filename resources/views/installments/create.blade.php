@extends('layouts.admin')
@section('page-title')
    {{ __('Create Installment') }}
@endsection
@section('title')
    {{ __('Create Installment') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('installments.index') }}">{{ __('Installments') }}</a></li>
    <li class="breadcrumb-item">{{ __('Create') }}</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-10 offset-md-1">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="ti ti-plus me-2"></i>{{ __('Create New Installment') }}</h5>
                </div>
                <div class="card-body p-4">
                    {{ Form::open(['url' => 'installments', 'method' => 'post', 'class' => 'needs-validation', 'novalidate']) }}

                    @if (isset($leadId) && $leadId)
                        {{ Form::hidden('lead_id', $leadId) }}
                    @endif

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <div class="alert alert-info">
                                <i class="ti ti-info-circle me-2"></i>
                                <strong>{{ __('Note:') }}</strong>
                                {{ __('Select the deal purchase and enter the installment amount. The system will track paid and unpaid amounts automatically.') }}
                            </div>
                        </div>

                        <div class="form-group col-md-12 mb-3">
                            {{ Form::label('deal_purchase_id', __('Deal Purchase'), ['class' => 'form-label fw-bold']) }}<x-required></x-required>
                            <select name="deal_purchase_id" id="deal_purchase_id" class="form-control select2" required>
                                <option value="">{{ __('Select Deal Purchase') }}</option>
                                @foreach ($dealPurchases as $dealPurchase)
                                    <option value="{{ $dealPurchase->id }}" data-price="{{ $dealPurchase->price }}"
                                        data-total-paid="{{ $dealPurchase->installments->sum('amount') }}">
                                        {{ $dealPurchase->deal ? $dealPurchase->deal->name : '#' . $dealPurchase->id }}
                                        - {{ \Auth::user()->priceFormat($dealPurchase->price) }}
                                        @if ($dealPurchase->deal && $dealPurchase->deal->lead)
                                            ({{ $dealPurchase->deal->lead->name }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            <small
                                class="form-text text-muted">{{ __('Choose the deal purchase for this installment') }}</small>
                        </div>

                        <div class="form-group col-md-6 mb-3">
                            {{ Form::label('amount', __('Installment Amount'), ['class' => 'form-label fw-bold']) }}<x-required></x-required>
                            <div class="input-group">
                                <span class="input-group-text"><i class="ti ti-currency-dollar"></i></span>
                                {{ Form::number('amount', null, ['class' => 'form-control', 'required' => 'required', 'step' => '0.01', 'id' => 'amount', 'placeholder' => '0.00']) }}
                                <span class="badge bg-primary pointer" id="max_amount_btn"
                                    style="cursor: pointer; display: none; align-self: center; margin-left: 5px;">{{ __('Max') }}</span>
                            </div>
                            <small id="amount_helper" class="form-text text-muted"></small>
                            <div class="invalid-feedback" id="amount_custom_error"></div>
                        </div>

                        <div class="form-group col-md-6 mb-3">
                            {{ Form::label('date', __('Due Date'), ['class' => 'form-label fw-bold']) }}<x-required></x-required>
                            <div class="input-group">
                                <span class="input-group-text"><i class="ti ti-calendar"></i></span>
                                {{ Form::date('date', null, ['class' => 'form-control', 'required' => 'required']) }}
                            </div>
                            <small class="form-text text-muted">{{ __('When is this installment due?') }}</small>
                        </div>

                        <div class="form-group col-md-6 mb-3">
                            {{ Form::label('status', __('Payment Status'), ['class' => 'form-label fw-bold']) }}<x-required></x-required>
                            <div class="input-group">
                                <span class="input-group-text"><i class="ti ti-check"></i></span>
                                {{ Form::select('status', ['unpaid' => __('Unpaid'), 'paid' => __('Paid')], 'unpaid', ['class' => 'form-control select2', 'required' => 'required']) }}
                            </div>
                            <small class="form-text text-muted">{{ __('Has this installment been paid?') }}</small>
                        </div>

                        <div class="form-group col-md-12 mb-3">
                            {{ Form::label('description', __('Description / Notes'), ['class' => 'form-label fw-bold']) }}
                            <div class="input-group">
                                <span class="input-group-text"><i class="ti ti-notes"></i></span>
                                {{ Form::textarea('description', null, ['class' => 'form-control', 'rows' => 4, 'placeholder' => __('Add any notes or details about this installment...')]) }}
                            </div>
                        </div>
                    </div>
                    <div class="text-end mt-4 pt-3 border-top">
                        <a href="{{ route('installments.index') }}" class="btn btn-secondary">
                            <i class="ti ti-arrow-left me-1"></i>{{ __('Cancel') }}
                        </a>
                        <input type="submit" value="{{ __('Create Installment') }}" class="btn btn-primary">
                    </div>
                    {{ Form::close() }}
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script-page')
    <script>
        // Real-time validation
        $(document).ready(function() {
        function updateAmountMax() {
            var selected = $('#deal_purchase_id').find('option:selected');
            if (selected.val()) {
                var price = parseFloat(selected.data('price')) || 0;
                var totalPaid = parseFloat(selected.data('total-paid')) || 0;
                var remaining = Math.max(0, price - totalPaid);

                // Round remaining to 2 decimals
                remaining = parseFloat(remaining.toFixed(2));

                $('#amount').attr('max', remaining);
                $('#amount_helper').text("{{ __('Remaining balance: ') }}" + remaining.toFixed(2) +
                    " (Price: " + price + ", Paid: " + totalPaid + ")");
                $('#max_amount_btn').show();
            } else {
                $('#amount').removeAttr('max');
                $('#amount_helper').text('');
                $('#max_amount_btn').hide();
            }
        }
        }

        // Bind events
        $('#deal_purchase_id').on('change', updateAmountMax);

        $('#max_amount_btn').on('click', function() {
        var selected = $('#deal_purchase_id').find('option:selected');
        if (selected.val()) {
            var price = parseFloat(selected.data('price')) || 0;
            var totalPaid = parseFloat(selected.data('total-paid')) || 0;
            var remaining = Math.max(0, price - totalPaid);
            remaining = parseFloat(remaining.toFixed(2));
            $('#amount').val(remaining);
            // reset custom error
            $('#amount_custom_error').text('').hide();
            if ($('#amount')[0].checkValidity()) {
                $('#amount').removeClass('is-invalid');
            }
        }
        });

        // Initial call
        updateAmountMax();

        // Form validation on submit
        var forms = document.querySelectorAll('.needs-validation'); Array.prototype.slice.call(forms).forEach(
        function(form) {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
        });
        });

        // Blur validation
        $('#amount').on('blur', function() {
            var selected = $('#deal_purchase_id').find('option:selected');
            var amountVal = parseFloat($(this).val()) || 0;

            if (selected.val()) {
                var price = parseFloat(selected.data('price')) || 0;
                var totalPaid = parseFloat(selected.data('total-paid')) || 0;

                // Allow a small epsilon for float comparison if needed, but rounding is safer
                var newTotal = parseFloat((totalPaid + amountVal).toFixed(2));

                if (newTotal > price) {
                    $(this).addClass('is-invalid');
                    // Ensure the error message is visible by removing valid-feedback class if present or handling class conflicts
                    var msg =
                        "{{ __('The value of all installments cannot exceed the total price in the deal purchase.') }}" +
                        " {{ __('Total Price:') }} " + price +
                        ", {{ __('Existing:') }} " + totalPaid +
                        ", {{ __('Current:') }} " + amountVal;
                    $('#amount_custom_error').text(msg).show();
                } else {
                    // Only remove is-invalid if browser validation also passes
                    if (this.checkValidity()) {
                        $(this).removeClass('is-invalid');
                    }
                    $('#amount_custom_error').text('').hide();
                }
            }
        });
    </script>
@endpush
