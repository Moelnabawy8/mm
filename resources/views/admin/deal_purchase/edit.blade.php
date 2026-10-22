@extends('layouts.admin')
@section('page-title')
    {{ __('Edit Deal Purchase') }}
@endsection
@section('title')
    {{ __('Edit Deal Purchase') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('deal-purchase.index') }}">{{ __('Deal Purchase') }}</a></li>
    <li class="breadcrumb-item">{{ __('Edit') }}</li>
@endsection

@push('css-page')
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/dropzone.min.css') }}">
    <style>
        .attachment-card {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .attachment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .attachment-card .attachment-preview {
            width: 100%;
            height: 100px;
            object-fit: cover;
            background-color: #f8f9fa;
        }

        .attachment-card .attachment-icon {
            width: 100%;
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-size: 2.5rem;
        }

        .attachment-card .attachment-info {
            padding: 8px;
            background: white;
        }

        .attachment-card .attachment-name {
            font-size: 0.8rem;
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .attachment-card .attachment-size {
            font-size: 0.7rem;
            color: #6c757d;
        }

        .attachment-card .attachment-actions {
            position: absolute;
            top: 5px;
            right: 5px;
            display: flex;
            gap: 3px;
        }

        .dropzone {
            border: 2px dashed #4b9cd3 !important;
            border-radius: 10px !important;
            background: rgba(75, 156, 211, 0.05) !important;
            min-height: 120px !important;
        }

        .dropzone:hover {
            background: rgba(75, 156, 211, 0.1) !important;
        }
    </style>
@endpush

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    {{ Form::model($dealPurchase, ['route' => ['deal-purchase.update', $dealPurchase->id], 'method' => 'PUT', 'class' => 'needs-validation', 'novalidate']) }}

                    @if (isset($leadId) && $leadId)
                        {{ Form::hidden('lead_id', $leadId) }}
                    @endif

                    <div class="row">
                        <div class="form-group col-md-6">
                            {{ Form::label('deal_id_search', __('Deal'), ['class' => 'form-label']) }}<x-required></x-required>
                            @include('sales_meetings._search_picker', [
                                'pickerId' => 'deal_id',
                                'fieldName' => 'deal_id',
                                'url' => route('deal-purchase.deals.search', [
                                    'mode' => 'free',
                                    'except' => $dealPurchase->deal_id,
                                ]),
                                'selectedId' => old('deal_id', $dealPurchase->deal_id),
                                'selectedName' => $selectedDeal->name ?? '',
                                'placeholder' => __('Search Deal'),
                                'required' => true,
                            ])
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
                            {{ Form::select('payment_type', ['one-time' => __('One-time'), 'installment' => __('Installment')], null, ['class' => 'form-control select2', 'required' => 'required', 'id' => 'payment_type']) }}
                        </div>

                        <div class="form-group col-md-6">
                            {{ Form::label('paid_amount', __('Paid Amount'), ['class' => 'form-label']) }}<x-required></x-required>
                            {{ Form::number('paid_amount', null, ['class' => 'form-control', 'required' => 'required', 'step' => '0.01', 'id' => 'paid_amount']) }}
                        </div>

                        <div class="form-group col-md-6">
                            {{ Form::label('status', __('Status'), ['class' => 'form-label']) }}<x-required></x-required>
                            {{ Form::select('status', ['pending' => __('Pending'), 'complete' => __('Complete')], null, ['class' => 'form-control select2', 'required' => 'required']) }}
                        </div>

                        <div class="form-group col-md-6">
                            {{ Form::label('purchase_date', __('Purchase Date'), ['class' => 'form-label']) }}<x-required></x-required>
                            {{ Form::date('purchase_date', date('Y-m-d', strtotime($dealPurchase->purchase_date)), ['class' => 'form-control', 'required' => 'required']) }}
                        </div>
                    </div>

                    {{-- Hidden field for existing installments data --}}
                    @php
                        $installmentsData = $dealPurchase->installments->map(function ($inst) {
                            return [
                                'id' => $inst->id,
                                'date' => date('Y-m-d\TH:i', strtotime($inst->date)),
                                'amount' => $inst->amount,
                                'description' => $inst->description,
                                'status' => $inst->status,
                                'is_new' => false,
                            ];
                        });
                    @endphp
                    <input type="hidden" id="existing-installments-data" value='{!! json_encode($installmentsData) !!}'>
                    {{-- Inline Installments Manager --}}
                    @include('components.installments-manager')

                    <div class="text-end mt-3">
                        {{ Form::submit(__('Update'), ['class' => 'btn btn-primary']) }}
                    </div>
                    {{ Form::close() }}
                </div>
            </div>
        </div>
    </div>

    {{-- Attachments Section --}}
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">{{ __('Attachments') }}</h5>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <form action="{{ route('deal-purchase.attachment.upload', $dealPurchase->id) }}" class="dropzone"
                            id="deal-purchase-dropzone">
                            @csrf
                        </form>
                    </div>

                    <div id="attachments-list" class="row g-3">
                        @foreach ($dealPurchase->attachments as $attachment)
                            <div class="col-md-2 col-sm-3 col-4" id="attachment-{{ $attachment->id }}">
                                <div class="card attachment-card mb-0 shadow-sm">
                                    @if ($attachment->isImage())
                                        <img src="{{ asset('storage/' . $attachment->file_path) }}"
                                            class="attachment-preview" alt="{{ $attachment->file_name }}">
                                    @else
                                        <div class="attachment-icon">
                                            <i class="ti ti-file-text"></i>
                                        </div>
                                    @endif
                                    <div class="attachment-info">
                                        <div class="attachment-name" title="{{ $attachment->file_name }}">
                                            {{ $attachment->file_name }}</div>
                                        <div class="attachment-size">{{ $attachment->formatted_size }}</div>
                                    </div>
                                    <div class="attachment-actions">
                                        <a href="{{ route('deal-purchase.attachment.serve', [$dealPurchase->id, $attachment->id]) }}"
                                            target="_blank" class="btn btn-xs btn-info" data-bs-toggle="tooltip"
                                            title="{{ __('View') }}">
                                            <i class="ti ti-eye"></i>
                                        </a>
                                        <a href="{{ route('deal-purchase.attachment.download', [$dealPurchase->id, $attachment->id]) }}"
                                            class="btn btn-xs btn-light" data-bs-toggle="tooltip"
                                            title="{{ __('Download') }}">
                                            <i class="ti ti-download"></i>
                                        </a>
                                        <button type="button" class="btn btn-xs btn-danger delete-attachment"
                                            data-id="{{ $attachment->id }}" data-bs-toggle="tooltip"
                                            title="{{ __('Delete') }}">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($dealPurchase->attachments->isEmpty())
                        <div id="no-attachments" class="text-center text-muted py-3">
                            <i class="ti ti-paperclip" style="font-size: 2rem;"></i>
                            <p class="mt-2 mb-0">{{ __('No attachments yet') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script-page')
    <script src="{{ asset('js/installments-manager.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/dropzone-amd-module.min.js') }}"></script>
    <script>
        function loadProducts(deal_id, selectedIds = []) {
            if (deal_id) {
                $.ajax({
                    url: '{{ route('deal-purchase.get-products', '') }}/' + deal_id,
                    type: 'GET',
                    success: function(data) {
                        var element = document.getElementById('products');
                        if (element.choices) {
                            try {
                                element.choices.destroy();
                            } catch (err) {
                                console.log('Choices destroy error ignored:', err);
                            }
                        }

                        var $select = $('#products');
                        $select.empty();

                        $.each(data, function(key, value) {
                            var label = value.name + (value.price ? ' (' + value.price + ')' : '');
                            $select.append('<option value="' + value.id + '">' +
                                label + '</option>');
                        });

                        var multipleCancelButton = new Choices("#products", {
                            removeItemButton: true,
                        });
                        element.choices = multipleCancelButton;

                        if (selectedIds && selectedIds.length > 0) {
                            // Ensure all IDs are strings for comparison/setting
                            var idsToString = selectedIds.map(String);
                            element.choices.setChoiceByValue(idsToString);
                        }
                    }
                });
            } else {
                var element = document.getElementById('products');
                if (element.choices) {
                    try {
                        element.choices.destroy();
                    } catch (err) {
                        console.log('Choices destroy error ignored:', err);
                    }
                    $('#products').empty();
                    var multipleCancelButton = new Choices("#products", {
                        removeItemButton: true,
                    });
                    element.choices = multipleCancelButton;
                }
            }
        }

        $(document).on('change', '#deal_id', function() {
            loadProducts($(this).val(), []);
        });

        function updatePaymentTypeUI(init = false) {
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
                if (installmentsManager && !init) {
                    Swal.fire({
                        title: '{{ __('Are you sure?') }}',
                        text: '{{ __('Switching to one-time payment will remove all installments.') }}',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: '{{ __('Yes, switch') }}',
                        cancelButtonText: '{{ __('No, cancel') }}',
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            installmentsManager.installments = [];
                            installmentsManager.renderInstallments();
                            installmentsManager.updateSummary();
                        } else {
                            // Revert back to installment
                            $('#payment_type').val('installment').trigger('change.select2');
                        }
                    });
                    return;
                }
            } else if (type === 'installment') {
                // Show paid amount field AND installments manager
                $paidAmountGroup.slideDown(300);
                $installmentsManager.slideDown(400);

                // Make paid amount EDITABLE and REQUIRED for installment type
                $paidAmountField.prop('readonly', false);
                $paidAmountField.prop('required', true);

                // Update installments manager with remaining balance (price - paid_amount)
                if (installmentsManager) {
                    var paidAmount = parseFloat($paidAmountField.val()) || 0;
                    installmentsManager.updateTotalPriceWithPaidAmount(price, paidAmount);
                    installmentsManager.updateSummary();

                    // If no installments exist and not initializing, suggest to user
                    if (!init && installmentsManager.installments.length === 0) {
                        setTimeout(function() {
                            Swal.fire({
                                title: '{{ __('Add Installment?') }}',
                                text: '{{ __('Would you like to add an installment now?') }}',
                                icon: 'question',
                                showCancelButton: true,
                                confirmButtonText: '{{ __('Yes, add') }}',
                                cancelButtonText: '{{ __('Not now') }}',
                                confirmButtonColor: '#28a745',
                                cancelButtonColor: '#6c757d',
                                reverseButtons: true
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    installmentsManager.showForm();
                                }
                            });
                        }, 500);
                    }
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
            // Load products with selected IDs
            loadProducts($('#deal_id').val(), {!! json_encode($dealPurchase->products ?? []) !!});

            // Initialize installments manager
            installmentsManager = new InstallmentsManager({
                dealPurchaseId: {{ $dealPurchase->id }},
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

            // Load existing installments
            installmentsManager.loadExistingInstallments();

            // Set initial state with paid_amount for installment type
            var initialPrice = parseFloat($('#price').val()) || 0;
            var initialPaidAmount = parseFloat($('#paid_amount').val()) || 0;
            var paymentType = $('#payment_type').val();

            if (paymentType === 'installment') {
                installmentsManager.updateTotalPriceWithPaidAmount(initialPrice, initialPaidAmount);
            } else {
                installmentsManager.updateTotalPrice(initialPrice);
            }

            // Set initial UI state
            updatePaymentTypeUI(true);

            // Initialize form validation (required for submit to work)
            if (typeof validation === 'function') {
                validation();
            }

            // Dropzone configuration
            Dropzone.options.dealPurchaseDropzone = {
                maxFilesize: 10, // MB
                acceptedFiles: 'image/*,.txt,.doc,.docx,.pdf',
                parallelUploads: 1,
                dictDefaultMessage: '<i class="ti ti-cloud-upload" style="font-size: 1.5rem;"></i><br>{{ __('Drag & drop files here or click to upload') }}',
                init: function() {
                    var dz = this;

                    this.on('sending', function(file, xhr, formData) {
                        formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
                    });

                    this.on('success', function(file, response) {
                        if (response.is_success) {
                            show_toastr('{{ __('Success') }}', response.success_msg, 'success');
                            // Add the new attachment to the list
                            var attachment = response.attachment;
                            var isImage = attachment.file_type === 'image';
                            var previewHtml = isImage ?
                                '<img src="{{ asset('storage') }}/' + attachment.file_path +
                                '" class="attachment-preview" alt="' + attachment.file_name + '">' :
                                '<div class="attachment-icon"><i class="ti ti-file-text"></i></div>';

                            var html = '<div class="col-md-2 col-sm-3 col-4" id="attachment-' +
                                attachment.id + '">' +
                                '<div class="card attachment-card mb-0 shadow-sm">' +
                                previewHtml +
                                '<div class="attachment-info">' +
                                '<div class="attachment-name" title="' + attachment.file_name +
                                '">' + attachment.file_name + '</div>' +
                                '<div class="attachment-size">' + formatFileSize(attachment
                                    .file_size) + '</div>' +
                                '</div>' +
                                '<div class="attachment-actions">' +
                                '<a href="{{ route('deal-purchase.attachment.serve', [$dealPurchase->id, '']) }}/' +
                                attachment.id +
                                '" target="_blank" class="btn btn-xs btn-info" data-bs-toggle="tooltip" title="{{ __('View') }}">' +
                                '<i class="ti ti-eye"></i></a>' +
                                '<a href="' + response.download +
                                '" class="btn btn-xs btn-light" data-bs-toggle="tooltip" title="{{ __('Download') }}">' +
                                '<i class="ti ti-download"></i></a>' +
                                '<button type="button" class="btn btn-xs btn-danger delete-attachment" data-id="' +
                                attachment.id +
                                '" data-bs-toggle="tooltip" title="{{ __('Delete') }}">' +
                                '<i class="ti ti-trash"></i></button>' +
                                '</div></div></div>';

                            $('#attachments-list').append(html);
                            $('#no-attachments').hide();
                            dz.removeFile(file);
                        } else {
                            show_toastr('{{ __('Error') }}', response.error, 'error');
                        }
                    });

                    this.on('error', function(file, message) {
                        show_toastr('{{ __('Error') }}', message, 'error');
                        dz.removeFile(file);
                    });
                }
            };

            function formatFileSize(bytes) {
                if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
                if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
                return bytes + ' bytes';
            }

            // Delete attachment
            $(document).on('click', '.delete-attachment', function() {
                var attachmentId = $(this).data('id');
                var $card = $('#attachment-' + attachmentId);

                Swal.fire({
                    title: '{{ __('Are you sure?') }}',
                    text: '{{ __('This action cannot be undone.') }}',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: '{{ __('Yes, delete it!') }}',
                    cancelButtonText: '{{ __('Cancel') }}'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: '{{ route('deal-purchase.attachment.delete', [$dealPurchase->id, '']) }}/' +
                                attachmentId,
                            type: 'DELETE',
                            data: {
                                _token: $('meta[name="csrf-token"]').attr('content')
                            },
                            success: function(response) {
                                if (response.is_success) {
                                    $card.fadeOut(300, function() {
                                        $(this).remove();
                                        if ($('#attachments-list .col-md-2')
                                            .length === 0) {
                                            $('#no-attachments').show();
                                        }
                                    });
                                    show_toastr('{{ __('Success') }}', response
                                        .success_msg, 'success');
                                } else {
                                    show_toastr('{{ __('Error') }}', response.error,
                                        'error');
                                }
                            },
                            error: function() {
                                show_toastr('{{ __('Error') }}',
                                    '{{ __('Something went wrong') }}', 'error');
                            }
                        });
                    }
                });
            });
        });
    </script>
@endpush
