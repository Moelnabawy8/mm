@extends('layouts.admin')
@section('page-title')
    {{ __('Edit Installment') }}
@endsection
@section('title')
    {{ __('Edit Installment') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('installments.index') }}">{{ __('Installments') }}</a></li>
    <li class="breadcrumb-item">{{ __('Edit') }}</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-10 offset-md-1">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="ti ti-pencil me-2"></i>{{ __('Edit Installment') }} #{{ $installment->id }}
                    </h5>
                </div>
                <div class="card-body p-4">
                    {{ Form::model($installment, ['route' => ['installments.update', $installment->id], 'method' => 'PUT', 'class' => 'needs-validation', 'novalidate']) }}

                    @if (isset($leadId) && $leadId)
                        {{ Form::hidden('lead_id', $leadId) }}
                    @endif

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <div class="alert alert-info">
                                <i class="ti ti-info-circle me-2"></i>
                                <strong>{{ __('Note:') }}</strong>
                                {{ __('Update the installment details. Changes will be reflected immediately.') }}
                            </div>
                        </div>

                        <div class="form-group col-md-12 mb-3">
                            {{ Form::label('deal_purchase_id', __('Deal Purchase'), ['class' => 'form-label fw-bold']) }}<x-required></x-required>
                            <select name="deal_purchase_id" id="deal_purchase_id" class="form-control select2" required>
                                <option value="">{{ __('Select Deal Purchase') }}</option>
                                @foreach ($dealPurchases as $dealPurchase)
                                    <option value="{{ $dealPurchase->id }}" data-price="{{ $dealPurchase->price }}"
                                        data-total-paid="{{ $dealPurchase->installments->sum('amount') }}"
                                        {{ $installment->deal_purchase_id == $dealPurchase->id ? 'selected' : '' }}>
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
                            {{ Form::label('exemption', __('Exemption Amount'), ['class' => 'form-label fw-bold']) }}
                            <i class="ti ti-info-circle text-info" data-bs-toggle="tooltip"
                                title="{{ __('Amount to forgive/exempt from this installment') }}"></i>
                            <div class="input-group">
                                <span class="input-group-text"><i class="ti ti-discount-2"></i></span>
                                {{ Form::number('exemption', null, ['class' => 'form-control', 'step' => '0.01', 'id' => 'exemption', 'placeholder' => '0.00', 'min' => '0']) }}
                            </div>
                            <small class="form-text text-info">
                                <i class="ti ti-calculator"></i> {{ __('Effective amount: ') }}
                                <strong id="effective_amount_display">0.00</strong>
                            </small>
                        </div>

                        <div class="form-group col-md-6 mb-3">
                            {{ Form::label('date', __('Due Date'), ['class' => 'form-label fw-bold']) }}<x-required></x-required>
                            <div class="input-group">
                                <span class="input-group-text"><i class="ti ti-calendar"></i></span>
                                {{ Form::date('date', date('Y-m-d', strtotime($installment->date)), ['class' => 'form-control', 'required' => 'required']) }}
                            </div>
                            <small class="form-text text-muted">{{ __('When is this installment due?') }}</small>
                        </div>

                        <div class="form-group col-md-6 mb-3">
                            {{ Form::label('status', __('Payment Status'), ['class' => 'form-label fw-bold']) }}<x-required></x-required>
                            <div class="input-group">
                                <span class="input-group-text"><i class="ti ti-check"></i></span>
                                {{ Form::select('status', ['unpaid' => __('Unpaid'), 'paid' => __('Paid')], $installment->status, ['class' => 'form-control select2', 'required' => 'required']) }}
                            </div>
                            <small class="form-text text-muted">{{ __('Has this installment been paid?') }}</small>
                        </div>

                        <div class="form-group col-md-12 mb-4">
                            <div class="card border-0" style="background-color: #f8f9fa;">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            {{ Form::label('is_hopeless', __('Hopeless Status'), ['class' => 'form-label fw-bold mb-1']) }}
                                            <i class="ti ti-info-circle text-danger" data-bs-toggle="tooltip"
                                                title="{{ __('Mark as hopeless if customer cannot pay this installment') }}"></i>
                                            <br>
                                            <small
                                                class="text-muted">{{ __('Toggle if this installment is uncollectable') }}</small>
                                        </div>
                                        <div class="form-check form-switch">
                                            {{ Form::hidden('is_hopeless', 0) }}
                                            {{ Form::checkbox('is_hopeless', 1, $installment->is_hopeless, ['class' => 'form-check-input', 'id' => 'is_hopeless', 'style' => 'width: 3.5rem; height: 1.75rem; cursor: pointer;']) }}
                                            <label class="form-check-label ms-2 fw-bold" for="is_hopeless"
                                                style="cursor: pointer; font-size: 1.1rem;">
                                                <span id="hopeless_label" class="badge"
                                                    style="font-size: 0.9rem;">{{ $installment->is_hopeless ? __('ON') : __('OFF') }}</span>
                                            </label>
                                        </div>
                                    </div>
                                    <div id="hopeless_warning" class="alert alert-danger mt-3 mb-0"
                                        style="{{ $installment->is_hopeless ? '' : 'display: none;' }}">
                                        <i class="ti ti-alert-triangle me-2"></i>
                                        <strong>{{ __('Warning:') }}</strong>
                                        {{ __('This installment is marked as uncollectable') }}
                                    </div>
                                </div>
                            </div>
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
                        <input type="submit" value="{{ __('Update Installment') }}" class="btn btn-primary">
                    </div>
                    {{ Form::close() }}
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script-page')
    <script>
        $(document).ready(function() {
            // Update Effective Amount Display
            function updateEffectiveAmount() {
                var amount = parseFloat($('#amount').val()) || 0;
                var exemption = parseFloat($('#exemption').val()) || 0;
                var effective = Math.max(0, amount - exemption);
                $('#effective_amount_display').text(effective.toFixed(2));
            }

            // Update Hopeless Label
            function updateHopelessLabel() {
                var isChecked = $('#is_hopeless').is(':checked');
                var label = $('#hopeless_label');

                if (isChecked) {
                    label.removeClass('badge-secondary').addClass('badge-danger').text('{{ __('ON') }}');
                    $('#hopeless_warning').slideDown();
                } else {
                    label.removeClass('badge-danger').addClass('badge-secondary').text('{{ __('OFF') }}');
                    $('#hopeless_warning').slideUp();
                }
            }

            // Get original installment amount (for edit mode)
            var originalAmount = parseFloat('{{ $installment->amount }}') || 0;

            // Real-time validation
            function updateAmountMax() {
                var selected = $('#deal_purchase_id').find('option:selected');
                if (selected.val()) {
                    var price = parseFloat(selected.data('price')) || 0;
                    var totalPaid = parseFloat(selected.data('total-paid')) || 0;

                    // Subtract current installment's original amount from total (we're editing it)
                    var otherInstallmentsTotal = totalPaid - originalAmount;
                    var remaining = Math.max(0, price - otherInstallmentsTotal);

                    // Round remaining to 2 decimals
                    remaining = parseFloat(remaining.toFixed(2));

                    $('#amount').attr('max', remaining);
                    $('#amount_helper').text("{{ __('Remaining balance: ') }}" + remaining.toFixed(2) +
                        " (Price: " + price + ", Other installments: " + otherInstallmentsTotal.toFixed(2) + ")"
                    );
                    $('#max_amount_btn').show();
                } else {
                    $('#amount').removeAttr('max');
                    $('#amount_helper').text('');
                    $('#max_amount_btn').hide();
                }
            }

            // Bind events
            $('#deal_purchase_id').on('change', updateAmountMax);
            $('#amount, #exemption').on('input', updateEffectiveAmount);
            $('#is_hopeless').on('change', updateHopelessLabel);

            $('#max_amount_btn').on('click', function() {
                var selected = $('#deal_purchase_id').find('option:selected');
                if (selected.val()) {
                    var price = parseFloat(selected.data('price')) || 0;
                    var totalPaid = parseFloat(selected.data('total-paid')) || 0;

                    // Subtract current installment's original amount
                    var otherInstallmentsTotal = totalPaid - originalAmount;
                    var remaining = Math.max(0, price - otherInstallmentsTotal);
                    remaining = parseFloat(remaining.toFixed(2));

                    $('#amount').val(remaining);
                    updateEffectiveAmount();
                    // reset custom error
                    $('#amount_custom_error').text('').hide();
                    if ($('#amount')[0].checkValidity()) {
                        $('#amount').removeClass('is-invalid');
                    }
                }
            });

            // Initial calls
            updateAmountMax();
            updateEffectiveAmount();
            updateHopelessLabel();

            // Form validation on submit
            var forms = document.querySelectorAll('.needs-validation');
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });

            // Blur validation
            $('#amount').on('blur', function() {
                var selected = $('#deal_purchase_id').find('option:selected');
                var amountVal = parseFloat($(this).val()) || 0;

                if (selected.val()) {
                    var price = parseFloat(selected.data('price')) || 0;
                    var totalPaid = parseFloat(selected.data('total-paid')) || 0;

                    // Subtract current installment's original amount (we're editing it)
                    var otherInstallmentsTotal = totalPaid - originalAmount;
                    var newTotal = parseFloat((otherInstallmentsTotal + amountVal).toFixed(2));

                    if (newTotal > price) {
                        $(this).addClass('is-invalid');
                        var msg =
                            "{{ __('The value of all installments cannot exceed the total price in the deal purchase.') }}" +
                            " {{ __('Total Price:') }} " + price +
                            ", {{ __('Other installments:') }} " + otherInstallmentsTotal.toFixed(2) +
                            ", {{ __('Current:') }} " + amountVal;
                        $('#amount_custom_error').text(msg).show();
                    } else {
                        if (this.checkValidity()) {
                            $(this).removeClass('is-invalid');
                        }
                        $('#amount_custom_error').text('').hide();
                    }
                }
            });

            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
@endpush
