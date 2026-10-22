@extends('layouts.admin')
@section('page-title')
    {{ __('Deal Purchase Details') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('deal-purchase.index') }}">{{ __('Deal Purchase') }}</a></li>
    <li class="breadcrumb-item">#{{ $dealPurchase->id }}</li>
@endsection

@push('css-page')
    <link rel="stylesheet" href="{{ asset('css/dropzone.min.css') }}">
    <style>
        .attachment-card {
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.2s;
        }

        .attachment-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .attachment-preview {
            width: 100%;
            height: 120px;
            object-fit: cover;
            background: #f8f9fa;
        }

        .attachment-icon {
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            color: #6c757d;
            font-size: 2.5rem;
        }

        .attachment-info {
            padding: 0.75rem;
            background: #fff;
        }

        .attachment-name {
            font-size: 0.85rem;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .attachment-size {
            font-size: 0.75rem;
            color: #6c757d;
        }

        .attachment-actions {
            display: flex;
            gap: 0.5rem;
            padding: 0 0.75rem 0.75rem;
        }
    </style>
@endpush

@push('script-page')
    <script src="{{ asset('assets/js/plugins/dropzone-amd-module.min.js') }}"></script>
    <script>
        Dropzone.autoDiscover = false;
        var purchaseDropzone = new Dropzone("#purchase-dropzone", {
            url: "{{ route('deal-purchase.attachment.upload', $dealPurchase->id) }}",
            maxFilesize: 10,
            acceptedFiles: ".jpeg,.jpg,.png,.gif,.webp,.txt,.doc,.docx,.pdf",
            addRemoveLinks: true,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(file, response) {
                if (response.is_success) {
                    show_toastr('Success', response.success_msg, 'success');
                    location.reload();
                }
            },
            error: function(file, response) {
                show_toastr('Error', response.error || 'Upload failed', 'error');
            }
        });

        $(document).on('click', '.delete-attachment', function() {
            var attachmentId = $(this).data('id');
            Swal.fire({
                title: '{{ __('Are you sure?') }}',
                text: '{{ __('This action cannot be undone.') }}',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '{{ __('Yes, delete it!') }}'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: "{{ url('deal-purchase') }}/{{ $dealPurchase->id }}/attachments/" +
                            attachmentId,
                        type: 'DELETE',
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(response) {
                            if (response.is_success) {
                                show_toastr('Success', response.success_msg, 'success');
                                $('#attachment-' + attachmentId).fadeOut(300, function() {
                                    $(this).remove();
                                });
                            }
                        },
                        error: function(response) {
                            show_toastr('Error', 'Delete failed', 'error');
                        }
                    });
                }
            });
        });
    </script>
@endpush

@section('action-btn')
    <div class="float-end">
        @can('edit deal')
            <a href="{{ route('deal-purchase.edit', $dealPurchase->id) }}" class="btn btn-sm btn-primary"
                data-bs-toggle="tooltip" title="{{ __('Edit') }}">
                <i class="ti ti-pencil"></i>
            </a>
        @endcan
    </div>
@endsection

@section('content')
    @php
        $installments = $dealPurchase->installments ?? collect([]);
        $totalInstallments = $installments->sum('amount');
        $paidInstallments = $installments->where('status', 'paid')->sum('amount');
        $unpaidInstallments = $installments->where('status', '!=', 'paid')->sum('amount');

        // Calculate total paid based on payment type
        if ($dealPurchase->payment_type == 'one-time') {
            // For one-time: paid_amount represents what was paid (usually = price)
            $upfrontPayment = $dealPurchase->paid_amount ?? $dealPurchase->price;
            $totalPaid = $upfrontPayment;
        } else {
            // For installment: paid_amount = upfront/initial payment + paid installments
            $upfrontPayment = $dealPurchase->paid_amount ?? 0;
            $totalPaid = $upfrontPayment + $paidInstallments;
        }

        $remaining = max(0, $dealPurchase->price - $totalPaid);
        $paidPercent = $dealPurchase->price > 0 ? min(100, round(($totalPaid / $dealPurchase->price) * 100)) : 0;
    @endphp

    <div class="crm-show-page">
        {{-- Hero Header --}}
        <div class="crm-hero">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
                <div>
                    <h1 class="crm-hero-title">
                        @if ($dealPurchase->deal)
                            {{ $dealPurchase->deal->name }}
                        @else
                            {{ __('Deal Purchase') }} #{{ $dealPurchase->id }}
                        @endif
                    </h1>
                    <div class="crm-hero-meta">
                        <span class="crm-hero-meta-item">
                            <i class="ti ti-currency-dollar"></i>
                            {{ $dealPurchase->currency }} {{ number_format($dealPurchase->price, 2) }}
                        </span>
                        <span class="crm-hero-meta-item">
                            <i class="ti ti-calendar"></i>
                            {{ \Auth::user()->dateFormat($dealPurchase->purchase_date) }}
                        </span>
                        @if ($dealPurchase->deal && $dealPurchase->deal->lead)
                            <span class="crm-hero-meta-item">
                                <i class="ti ti-user"></i>
                                <a
                                    href="{{ route('leads.show', $dealPurchase->deal->lead->id) }}">{{ $dealPurchase->deal->lead->name }}</a>
                            </span>
                        @endif
                    </div>
                </div>
                @if ($dealPurchase->status)
                    @if ($dealPurchase->status == 'complete')
                        <span class="crm-status success"><i class="ti ti-check"></i> {{ __('Complete') }}</span>
                    @else
                        <span class="crm-status info">{{ __('Pending') }}</span>
                    @endif
                @endif
            </div>

            {{-- Payment Progress --}}
            @if ($dealPurchase->price > 0)
                <div class="mt-4 p-3 rounded" style="background: #f8f9fa;">
                    <div class="d-flex justify-content-between mb-2 small">
                        <span class="text-muted">{{ __('Payment Progress') }}</span>
                        <span class="fw-bold">{{ $paidPercent }}%</span>
                    </div>
                    <div class="crm-progress-bar" style="height: 8px;">
                        <div class="crm-progress-fill" style="width: {{ $paidPercent }}%"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-2 small">
                        <span><span class="text-success fw-medium">{{ $dealPurchase->currency }}
                                {{ number_format($totalPaid, 2) }}</span> {{ __('paid') }}</span>
                        <span><span class="text-warning fw-medium">{{ $dealPurchase->currency }}
                                {{ number_format($remaining, 2) }}</span> {{ __('remaining') }}</span>
                    </div>
                </div>
            @endif
        </div>

        {{-- Two Column Layout --}}
        <div class="crm-layout">
            {{-- Left: Quick Panel --}}
            <div class="crm-quick-panel">
                {{-- Purchase Info --}}
                <div class="crm-quick-card">
                    <div class="crm-quick-card-title">{{ __('Purchase Details') }}</div>
                    <div class="crm-info-list">
                        @if ($dealPurchase->deal)
                            <div class="crm-info-item">
                                <span class="crm-info-label">{{ __('Deal') }}</span>
                                <span class="crm-info-value">
                                    <a
                                        href="{{ route('deals.show', $dealPurchase->deal->id) }}">{{ $dealPurchase->deal->name }}</a>
                                </span>
                            </div>
                        @endif
                        @if ($dealPurchase->deal && $dealPurchase->deal->lead)
                            <div class="crm-info-item">
                                <span class="crm-info-label">{{ __('Lead') }}</span>
                                <span class="crm-info-value">
                                    <a
                                        href="{{ route('leads.show', $dealPurchase->deal->lead->id) }}">{{ $dealPurchase->deal->lead->name }}</a>
                                </span>
                            </div>
                        @endif
                        <div class="crm-info-item">
                            <span class="crm-info-label">{{ __('Price') }}</span>
                            <span class="crm-info-value">{{ $dealPurchase->currency }}
                                {{ number_format($dealPurchase->price, 2) }}</span>
                        </div>
                        @if ($dealPurchase->payment_type == 'installment' && $upfrontPayment > 0)
                            <div class="crm-info-item">
                                <span class="crm-info-label">{{ __('Upfront Payment') }}</span>
                                <span class="crm-info-value text-info">{{ $dealPurchase->currency }}
                                    {{ number_format($upfrontPayment, 2) }}</span>
                            </div>
                        @endif
                        <div class="crm-info-item">
                            <span class="crm-info-label">{{ __('Total Paid') }}</span>
                            <span class="crm-info-value text-success">{{ $dealPurchase->currency }}
                                {{ number_format($totalPaid, 2) }}</span>
                        </div>
                        <div class="crm-info-item">
                            <span class="crm-info-label">{{ __('Purchase Date') }}</span>
                            <span
                                class="crm-info-value">{{ \Auth::user()->dateFormat($dealPurchase->purchase_date) }}</span>
                        </div>
                    </div>
                </div>

                {{-- Payment Info --}}
                <div class="crm-quick-card">
                    <div class="crm-quick-card-title">{{ __('Payment Info') }}</div>
                    <div class="crm-info-list">
                        @if ($dealPurchase->payment_method)
                            <div class="crm-info-item">
                                <span class="crm-info-label">{{ __('Method') }}</span>
                                <span
                                    class="crm-info-value">{{ $dealPurchase->paymentMethodDetails->name ?? $dealPurchase->payment_method }}</span>
                            </div>
                        @endif
                        @if ($dealPurchase->payment_type)
                            <div class="crm-info-item">
                                <span class="crm-info-label">{{ __('Type') }}</span>
                                <span class="crm-info-value">{{ ucfirst($dealPurchase->payment_type) }}</span>
                            </div>
                        @endif
                        <div class="crm-info-item">
                            <span class="crm-info-label">{{ __('Status') }}</span>
                            <span class="crm-info-value">
                                @if ($dealPurchase->status == 'complete')
                                    <span class="badge bg-success">{{ __('Complete') }}</span>
                                @else
                                    <span class="badge bg-secondary">{{ __('Pending') }}</span>
                                @endif
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Attachments Count --}}
                @if ($dealPurchase->attachments && $dealPurchase->attachments->count() > 0)
                    <div class="crm-quick-card">
                        <div class="crm-quick-card-title">{{ __('Attachments') }}</div>
                        <div class="d-flex align-items-center gap-2">
                            <i class="ti ti-paperclip text-muted"></i>
                            <span>{{ $dealPurchase->attachments->count() }} {{ __('files') }}</span>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Right: Main Content --}}
            <div class="crm-main-content">
                {{-- Installments Section --}}
                <div class="crm-content-section">
                    <div class="crm-content-header">
                        <h5 class="crm-content-title">
                            <i class="ti ti-cash"></i>
                            {{ __('Installments') }}
                            @if ($installments->count() > 0)
                                <span class="crm-content-count">{{ $installments->count() }}</span>
                            @endif
                        </h5>
                        @can('create deal')
                            <a href="{{ route('installments.create') }}?deal_purchase_id={{ $dealPurchase->id }}"
                                class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="{{ __('Add Installment') }}">
                                <i class="ti ti-plus"></i>
                            </a>
                        @endcan
                    </div>
                    <div class="crm-content-body no-padding">
                        @if ($installments->count() > 0)
                            <table class="crm-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Amount') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th>{{ __('Date') }}</th>
                                        <th>{{ __('Description') }}</th>
                                        <th style="width: 100px;">{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($installments as $installment)
                                        <tr>
                                            <td class="fw-medium">{{ $dealPurchase->currency }}
                                                {{ number_format($installment->amount, 2) }}</td>
                                            <td>
                                                @if ($installment->status == 'paid')
                                                    <span class="crm-status success">{{ __('Paid') }}</span>
                                                @else
                                                    <span class="crm-status warning">{{ __('Unpaid') }}</span>
                                                @endif
                                            </td>
                                            <td>{{ \Auth::user()->dateFormat($installment->date) }}</td>
                                            <td class="text-truncate" style="max-width: 150px;">
                                                {{ $installment->description ?? '-' }}</td>
                                            <td>
                                                <div class="crm-actions">
                                                    <a href="{{ route('installments.show', $installment->id) }}"
                                                        class="crm-action-btn view" data-bs-toggle="tooltip"
                                                        title="{{ __('View') }}">
                                                        <i class="ti ti-eye"></i>
                                                    </a>
                                                    @can('edit deal')
                                                        <a href="{{ route('installments.edit', $installment->id) }}"
                                                            class="crm-action-btn edit" data-bs-toggle="tooltip"
                                                            title="{{ __('Edit') }}">
                                                            <i class="ti ti-pencil"></i>
                                                        </a>
                                                    @endcan
                                                    @can('delete deal')
                                                        {!! Form::open([
                                                            'method' => 'DELETE',
                                                            'route' => ['installments.destroy', $installment->id],
                                                            'class' => 'd-inline',
                                                        ]) !!}
                                                        <button type="submit" class="crm-action-btn delete bs-pass-para"
                                                            data-bs-toggle="tooltip" title="{{ __('Delete') }}">
                                                            <i class="ti ti-trash"></i>
                                                        </button>
                                                        {!! Form::close() !!}
                                                    @endcan
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="crm-empty">
                                <i class="ti ti-cash"></i>
                                <p>{{ __('No installments yet') }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Attachments Section --}}
                <div class="crm-content-section">
                    <div class="crm-content-header">
                        <h5 class="crm-content-title">
                            <i class="ti ti-paperclip"></i>
                            {{ __('Attachments') }}
                            @if ($dealPurchase->attachments && $dealPurchase->attachments->count() > 0)
                                <span class="crm-content-count">{{ $dealPurchase->attachments->count() }}</span>
                            @endif
                        </h5>
                    </div>
                    <div class="crm-content-body">
                        <div class="mb-4">
                            <form action="{{ route('deal-purchase.attachment.upload', $dealPurchase->id) }}"
                                class="dropzone" id="purchase-dropzone"
                                style="min-height: 150px; border: 2px dashed #dee2e6; border-radius: 8px; background: #f8f9fa;">
                                @csrf
                                <div class="dz-message" data-dz-message>
                                    <div class="text-center py-4">
                                        <i class="ti ti-cloud-upload fs-1 text-muted mb-2"></i>
                                        <p class="mb-0 text-muted">{{ __('Drop files here or click to upload') }}</p>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div id="attachments-list" class="row g-3">
                            @foreach ($dealPurchase->attachments ?? [] as $attachment)
                                <div class="col-md-3 col-sm-4 col-6" id="attachment-{{ $attachment->id }}">
                                    <div class="card attachment-card mb-0 shadow-sm">
                                        @if ($attachment->isImage())
                                            <img src="{{ route('deal-purchase.attachment.serve', [$dealPurchase->id, $attachment->id]) }}"
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
                                            <a href="{{ route('deal-purchase.attachment.download', [$dealPurchase->id, $attachment->id]) }}"
                                                class="btn btn-sm btn-light" data-bs-toggle="tooltip"
                                                title="{{ __('Download') }}">
                                                <i class="ti ti-download"></i>
                                            </a>
                                            @can('edit deal')
                                                <button type="button" class="btn btn-sm btn-danger delete-attachment"
                                                    data-id="{{ $attachment->id }}" data-bs-toggle="tooltip"
                                                    title="{{ __('Delete') }}">
                                                    <i class="ti ti-trash"></i>
                                                </button>
                                            @endcan
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
