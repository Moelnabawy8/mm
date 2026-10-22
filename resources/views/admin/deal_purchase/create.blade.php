@extends('layouts.admin')
@section('page-title')
    {{ __('Create Deal Purchase') }}
@endsection
@section('title')
    {{ __('Create Deal Purchase') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('deal-purchase.index') }}">{{ __('Deal Purchase') }}</a></li>
    <li class="breadcrumb-item">{{ __('Create') }}</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    @if ($dealsCount == 0)
                        <div class="alert alert-warning" role="alert">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>{{ __('No Deals Available') }}</strong><br>
                            {{ __('All deals already have purchase records. Each deal can only have one purchase. Please create a new deal first or edit an existing purchase.') }}
                        </div>
                        <div class="text-end">
                            <a href="{{ route('deals.index') }}" class="btn btn-primary">
                                <i class="fas fa-plus"></i> {{ __('Go to Deals') }}
                            </a>
                        </div>
                    @else
                        {{ Form::open(['url' => 'deal-purchase', 'method' => 'post', 'class' => 'needs-validation', 'novalidate']) }}

                        @if (isset($leadId) && $leadId)
                            {{ Form::hidden('lead_id', $leadId) }}
                        @endif

                        <div class="row">
                            <div class="form-group col-md-6">
                                @if (!is_null($leadDeals))
                                    {{-- Opened from a lead: the candidates are few and already
                                         known, so they are simply listed. Deliberately not
                                         .select2 -- custom.js would wrap it in a Choices widget
                                         and the change handler below reads this element. --}}
                                    {{ Form::label('deal_id', __('Deal'), ['class' => 'form-label']) }}<x-required></x-required>
                                    <select class="form-control" id="deal_id" name="deal_id" required>
                                        @if ($leadDeals->count() > 1)
                                            <option value="">{{ __('Select Deal') }}</option>
                                        @endif
                                        @foreach ($leadDeals as $leadDeal)
                                            <option value="{{ $leadDeal->id }}"
                                                @selected((string) old('deal_id', $selectedDeal->id ?? '') === (string) $leadDeal->id)>
                                                {{ $leadDeal->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    {{ Form::label('deal_id_search', __('Deal'), ['class' => 'form-label']) }}<x-required></x-required>
                                    @include('sales_meetings._search_picker', [
                                        'pickerId' => 'deal_id',
                                        'fieldName' => 'deal_id',
                                        'url' => route(
                                            'deal-purchase.deals.search',
                                            array_filter(['mode' => 'free', 'lead_id' => $leadId]),
                                        ),
                                        'selectedId' => old('deal_id', $selectedDeal->id ?? null),
                                        'selectedName' => $selectedDeal->name ?? '',
                                        'placeholder' => __('Search Deal'),
                                        'required' => true,
                                    ])
                                @endif
                            </div>

                            <div class="form-group col-md-6">
                                {{ Form::label('products', __('Products'), ['class' => 'form-label']) }}
                                {{ Form::select('products[]', [], null, ['class' => 'form-control', 'multiple' => 'multiple', 'id' => 'products', 'data-placeholder' => __('Select Products')]) }}
                            </div>

                            <div class="form-group col-md-4">
                                {{ Form::label('price', __('Total Price'), ['class' => 'form-label']) }}<x-required></x-required>
                                {{ Form::number('price', null, ['class' => 'form-control', 'required' => 'required', 'step' => '0.01', 'id' => 'price']) }}
                            </div>

                            <div class="form-group col-md-4">
                                {{ Form::label('currency', __('Currency'), ['class' => 'form-label']) }}<x-required></x-required>
                                {{ Form::select('currency', $currencies, null, ['class' => 'form-control select2', 'required' => 'required', 'id' => 'currency']) }}
                            </div>

                            <div class="form-group col-md-4">
                                {{ Form::label('payment_method', __('Payment Method'), ['class' => 'form-label']) }}<x-required></x-required>
                                {{ Form::select('payment_method', $paymentMethods, null, ['class' => 'form-control select2', 'required' => 'required', 'placeholder' => __('Select Payment Method')]) }}
                            </div>

                            <div class="form-group col-md-6">
                                {{ Form::label('payment_type', __('Payment Type'), ['class' => 'form-label']) }}<x-required></x-required>
                                {{ Form::select('payment_type', ['one-time' => __('One-time'), 'installment' => __('Installment')], 'one-time', ['class' => 'form-control select2', 'required' => 'required', 'id' => 'payment_type']) }}
                            </div>

                            <div class="form-group col-md-6">
                                {{ Form::label('paid_amount', __('Paid Amount'), ['class' => 'form-label']) }}<x-required></x-required>
                                {{ Form::number('paid_amount', null, ['class' => 'form-control', 'required' => 'required', 'step' => '0.01', 'id' => 'paid_amount']) }}
                            </div>

                            <div class="form-group col-md-6">
                                {{ Form::label('status', __('Status'), ['class' => 'form-label']) }}<x-required></x-required>
                                {{ Form::select('status', ['pending' => __('Pending'), 'complete' => __('Complete')], 'pending', ['class' => 'form-control select2', 'required' => 'required']) }}
                            </div>

                            <div class="form-group col-md-6">
                                {{ Form::label('purchase_date', __('Purchase Date'), ['class' => 'form-label']) }}<x-required></x-required>
                                {{ Form::date('purchase_date', date('Y-m-d'), ['class' => 'form-control', 'required' => 'required']) }}
                            </div>
                        </div>

                        {{-- Attachments Info --}}
                        <div class="alert alert-secondary d-flex align-items-center mt-3" role="alert">
                            <i class="ti ti-info-circle me-2" style="font-size: 1.2rem;"></i>
                            <small>{{ __('You can add attachments after saving the deal purchase.') }}</small>
                        </div>

                        {{-- Inline Installments Manager --}}
                        @include('components.installments-manager')

                        <div class="text-end mt-3">
                            {{ Form::submit(__('Create'), ['class' => 'btn btn-primary']) }}
                        </div>
                        {{ Form::close() }}
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script-page')
    <script src="{{ asset('js/installments-manager.js') }}"></script>
    <script>
        // Same loader the edit screen uses. This page only ever ran it from the
        // Deal change handler, so the field stayed a bare <select multiple> until
        // a deal was picked -- an empty box with no placeholder -- and a deal that
        // arrived already selected (coming from a deal or a failed submit) never
        // loaded its products at all.
        function initProductsChoices() {
            var element = document.getElementById('products');
            if (!element) {
                return null;
            }
            if (element.choices) {
                try {
                    element.choices.destroy();
                } catch (err) {
                    console.log('Choices destroy error ignored:', err);
                }
                element.choices = null;
            }
            element.choices = new Choices('#products', {
                removeItemButton: true,
                placeholder: true,
                placeholderValue: $(element).data('placeholder') || '',
            });

            return element;
        }

        function loadProducts(deal_id, selectedIds) {
            selectedIds = selectedIds || [];

            if (!deal_id) {
                $('#products').empty();
                initProductsChoices();
                return;
            }

            $.ajax({
                url: '{{ route('deal-purchase.get-products', '') }}/' + deal_id,
                type: 'GET',
                success: function(data) {
                    var element = document.getElementById('products');
                    if (element && element.choices) {
                        try {
                            element.choices.destroy();
                        } catch (err) {
                            console.log('Choices destroy error ignored:', err);
                        }
                        element.choices = null;
                    }

                    var $select = $('#products');
                    $select.empty();

                    $.each(data, function(key, value) {
                        var label = value.name + (value.price ? ' (' + value.price + ')' : '');
                        // built as a node, not a string: a product name holding a
                        // quote or an angle bracket used to break the markup.
                        $select.append($('<option></option>').attr('value', value.id).text(label));
                    });

                    element = initProductsChoices();

                    if (element && element.choices && selectedIds.length > 0) {
                        element.choices.setChoiceByValue(selectedIds.map(String));
                    }
                },
                error: function() {
                    $('#products').empty();
                    initProductsChoices();
                }
            });
        }

        $(document).on('change', '#deal_id', function() {
            loadProducts($(this).val(), []);
        });

        $(document).ready(function() {
            loadProducts($('#deal_id').val(), {!! json_encode(old('products', [])) !!});
        });

        function updatePaymentTypeUI() {
            var type = $('#payment_type').val();
            var price = parseFloat($('#price').val()) || 0;
            var $paidAmountGroup = $('#paid_amount').closest('.form-group');
            var $paidAmountField = $('#paid_amount');
            var $installmentsManager = $('#installments-manager-wrapper');

            if (type === 'one-time') {
                // Show paid amount field, hide installments manager
                $paidAmountGroup.slideDown(300);
                $installmentsManager.slideUp(300);

                // A one-time purchase is paid in full: the controller always stores
                // paid_amount = price, so leaving the field editable silently threw
                // away whatever the user typed. Show it read-only instead.
                $paidAmountField.val(price).prop('readonly', true);

                // Make it REQUIRED for validation
                $paidAmountField.prop('required', true);

                // Clear installments if switching from installment to one-time
                if (installmentsManager) {
                    installmentsManager.installments = [];
                    installmentsManager.renderInstallments();
                    installmentsManager.updateSummary();
                }
            } else if (type === 'installment') {
                // Show paid amount field AND installments manager
                $paidAmountGroup.slideDown(300);
                $installmentsManager.slideDown(400);

                // Make paid amount EDITABLE and REQUIRED for installment type
                $paidAmountField.prop('readonly', false);
                $paidAmountField.prop('required', true);

                // Set default paid amount to 0 if empty
                if (!$paidAmountField.val() || parseFloat($paidAmountField.val()) === price) {
                    $paidAmountField.val(0);
                }

                // Update installments manager with remaining balance (price - paid_amount)
                if (installmentsManager) {
                    var paidAmount = parseFloat($paidAmountField.val()) || 0;
                    installmentsManager.updateTotalPriceWithPaidAmount(price, paidAmount);
                    installmentsManager.updateSummary();
                }
            }
        }

        $(document).on('change', '#payment_type', function() {
            updatePaymentTypeUI();
        });

        $(document).on('keyup change', '#price', function() {
            var price = parseFloat($(this).val()) || 0;
            var paidAmount = parseFloat($('#paid_amount').val()) || 0;

            // Update paid amount max validation
            $('#paid_amount').attr('max', price);

            // If One-time, sync value
            if ($('#payment_type').val() === 'one-time') {
                $('#paid_amount').val(price);
            } else if ($('#payment_type').val() === 'installment') {
                // Update installments manager with new total price minus paid amount
                if (installmentsManager) {
                    installmentsManager.updateTotalPriceWithPaidAmount(price, paidAmount);
                    installmentsManager.updateSummary();
                }
            }
        });

        // Handle paid_amount changes for installment type
        $(document).on('keyup change', '#paid_amount', function() {
            if ($('#payment_type').val() === 'installment') {
                var price = parseFloat($('#price').val()) || 0;
                var paidAmount = parseFloat($(this).val()) || 0;

                // Ensure paid amount doesn't exceed price
                if (paidAmount > price) {
                    $(this).val(price);
                    paidAmount = price;
                }

                // Update installments manager with remaining balance
                if (installmentsManager) {
                    installmentsManager.updateTotalPriceWithPaidAmount(price, paidAmount);
                    installmentsManager.updateSummary();
                }
            }
        });

        $(document).on('change', '#currency', function() {
            var currency = $(this).val();
            if (installmentsManager) {
                installmentsManager.currency = currency;
                installmentsManager.updateSummary();
            }
        });

        // Initialize
        $(document).ready(function() {
            // Initialize installments manager
            installmentsManager = new InstallmentsManager({
                dealPurchaseId: null,
                currency: $('#currency').val() || '',
                translations: {
                    editInstallment: "{{ __('Edit Installment') }}",
                    addNewInstallment: "{{ __('Add New Installment') }}",
                    amountExceedsBalance: "{{ __('Amount exceeds remaining balance') }}",
                    pleaseSelectDate: "{{ __('Please select a date') }}",
                    installmentUpdated: "{{ __('Installment updated successfully') }}",
                    installmentAdded: "{{ __('Installment added successfully') }}",
                    confirmDelete: "{{ __('Are you sure?') }}",
                    confirmDeleteText: "{{ __('This action cannot be undone. Do you want to continue?') }}",
                    confirmYes: "{{ __('Yes') }}",
                    confirmNo: "{{ __('No') }}",
                    installmentDeleted: "{{ __('Installment deleted successfully') }}",
                    noInstallments: "{{ __('No installments added yet. Click \"Add Installment\" to get started.') }}",
                    noDescription: "{{ __('No description') }}",
                    edit: "{{ __('Edit') }}",
                    deleteBtn: "{{ __('Delete') }}",
                    remainingBalance: "{{ __('Remaining balance: ') }}",
                    paid: "{{ __('Paid') }}",
                    unpaid: "{{ __('Unpaid') }}"
                }
            });

            // Set initial state
            var initialPrice = parseFloat($('#price').val()) || 0;
            installmentsManager.updateTotalPrice(initialPrice);

            // Set initial UI state
            updatePaymentTypeUI();

            // Ensure required attributes are set correctly on initial load
            var initialPaymentType = $('#payment_type').val();
            if (initialPaymentType === 'one-time') {
                $('#paid_amount').prop('required', true);
            } else {
                $('#paid_amount').prop('required', false);
            }

            // IMPORTANT: Initialize form validation FIRST (before any custom handlers)
            // This ensures the validation() submit handler is attached before ours
            if (typeof validation === 'function') {
                validation();
                console.log('✅ Form validation initialized');
            } else {
                console.error('❌ validation() function not found!');
            }

            // Debug: Add form submit listener to see what's happening
            // This runs AFTER validation() handler due to event listener order
            $('form.needs-validation').on('submit', function(e) {
                console.log('🚀 Form submit event fired!');
                console.log('📝 Form validity:', this.checkValidity());
                console.log('📋 Payment type:', $('#payment_type').val());
                console.log('💰 Paid amount:', $('#paid_amount').val(), 'Required:', $('#paid_amount').prop(
                    'required'));

                // Check all required fields
                var invalidFields = [];
                $(this).find('[required]').each(function() {
                    if (!this.checkValidity()) {
                        invalidFields.push({
                            name: $(this).attr('name') || $(this).attr('id'),
                            value: $(this).val(),
                            validationMessage: this.validationMessage
                        });
                    }
                });

                if (invalidFields.length > 0) {
                    console.error('❌ Invalid fields:', invalidFields);
                } else {
                    console.log('✅ All fields valid!');
                }
            });

            // Debug: Log form state
            console.log('📋 Form State:', {
                paymentType: $('#payment_type').val(),
                paidAmountRequired: $('#paid_amount').prop('required'),
                paidAmountVisible: $('#paid_amount').closest('.form-group').is(':visible'),
                paidAmountValue: $('#paid_amount').val()
            });
        });
    </script>
@endpush
