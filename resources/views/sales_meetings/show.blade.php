@extends('layouts.admin')
@section('page-title')
    {{ $meeting->title }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('sales-meetings.index') }}">{{ __('Sales Meetings') }}</a></li>
    <li class="breadcrumb-item">{{ $meeting->title }}</li>
@endsection

@push('css-page')
    <link rel="stylesheet" href="{{ asset('css/summernote/summernote-bs4.css') }}">
@endpush
@push('script-page')
    <script src="{{ asset('css/summernote/summernote-bs4.js') }}"></script>
    <script>
        $(document).ready(function() {
            @can('edit lead')
                $('.summernote-simple').on('summernote.blur', function() {
                    $.ajax({
                        url: "{{ route('sales-meetings.note.store', $meeting->id) }}",
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content'),
                            notes: $(this).val()
                        },
                        type: 'POST',
                        success: function(response) {
                            if (response.is_success) {
                                show_toastr('Success', response.success, 'success');
                            } else {
                                show_toastr('error', response.error, 'error');
                            }
                        },
                        error: function(response) {
                            response = response.responseJSON || {};
                            show_toastr('error', response.error || '{{ __('Something went wrong.') }}', 'error');
                        }
                    })
                });
            @else
                $('.summernote-simple').summernote('disable');
            @endcan
        });
    </script>
@endpush

@section('action-btn')
    <div class="float-end">
        @can('edit lead')
            <a href="#" data-url="{{ route('sales-meetings.edit', $meeting->id) }}" data-ajax-popup="true" data-size="lg"
                data-title="{{ __('Edit Meeting') }}" data-bs-toggle="tooltip" title="{{ __('Edit') }}"
                class="btn btn-sm btn-primary">
                <i class="ti ti-pencil"></i>
            </a>
        @endcan
    </div>
@endsection

@php
    // Date-only meetings are stored at midnight; do not invent a 12:00 AM for them.
    $meetingHasTime = $meeting->datetime && $meeting->datetime->format('H:i') !== '00:00';
@endphp

@section('content')
    <div class="crm-show-page">
        {{-- Hero Header --}}
        <div class="crm-hero">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
                <div>
                    <h1 class="crm-hero-title">{{ $meeting->title }}</h1>
                    <div class="crm-hero-meta">
                        @if ($meeting->meetingType)
                            <span class="crm-hero-meta-item">
                                <i class="ti ti-video"></i>
                                {{ $meeting->meetingType->name }}
                            </span>
                        @endif
                        <span class="crm-hero-meta-item">
                            <i class="ti ti-calendar-event"></i>
                            @if ($meeting->datetime)
                                {{ \Auth::user()->dateFormat($meeting->datetime) }}
                                @if ($meetingHasTime)
                                    {{ $meeting->datetime->format('h:i A') }}
                                @endif
                            @elseif ($meeting->date)
                                {{ \Auth::user()->dateFormat($meeting->date) }} {{ $meeting->time }}
                            @else
                                -
                            @endif
                        </span>
                        @if ($meeting->duration)
                            <span class="crm-hero-meta-item">
                                <i class="ti ti-clock"></i>
                                {{ $meeting->duration }} {{ __('min') }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Two Column Layout --}}
        <div class="crm-layout">
            {{-- Left: Quick Panel --}}
            <div class="crm-quick-panel">
                {{-- Meeting Info --}}
                <div class="crm-quick-card">
                    <div class="crm-quick-card-title">{{ __('Meeting Details') }}</div>
                    <div class="crm-info-list">
                        @if ($meeting->meetingType)
                            <div class="crm-info-item">
                                <span class="crm-info-label">{{ __('Type') }}</span>
                                <span class="crm-info-value">{{ $meeting->meetingType->name }}</span>
                            </div>
                        @endif
                        <div class="crm-info-item">
                            <span class="crm-info-label">{{ __('Date') }}</span>
                            <span class="crm-info-value">
                                @if ($meeting->datetime)
                                    {{ \Auth::user()->dateFormat($meeting->datetime) }}
                                @elseif ($meeting->date)
                                    {{ \Auth::user()->dateFormat($meeting->date) }}
                                @else
                                    -
                                @endif
                            </span>
                        </div>
                        <div class="crm-info-item">
                            <span class="crm-info-label">{{ __('Time') }}</span>
                            <span class="crm-info-value">
                                @if ($meetingHasTime)
                                    {{ $meeting->datetime->format('h:i A') }}
                                @elseif (!$meeting->datetime && $meeting->time)
                                    {{ $meeting->time }}
                                @else
                                    -
                                @endif
                            </span>
                        </div>
                        @if ($meeting->duration)
                            <div class="crm-info-item">
                                <span class="crm-info-label">{{ __('Duration') }}</span>
                                <span class="crm-info-value">{{ $meeting->duration }} {{ __('minutes') }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Related Items --}}
                <div class="crm-quick-card">
                    <div class="crm-quick-card-title">{{ __('Related To') }}</div>
                    <div class="crm-info-list">
                        @if ($meeting->lead)
                            <div class="crm-info-item">
                                <span class="crm-info-label">{{ __('Lead') }}</span>
                                <span class="crm-info-value">
                                    <a href="{{ route('leads.show', $meeting->lead->id) }}">{{ $meeting->lead->name }}</a>
                                </span>
                            </div>
                        @endif
                        @if ($meeting->deal)
                            <div class="crm-info-item">
                                <span class="crm-info-label">{{ __('Deal') }}</span>
                                <span class="crm-info-value">
                                    <a href="{{ route('deals.show', $meeting->deal->id) }}">{{ $meeting->deal->name }}</a>
                                </span>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Meeting Link --}}
                @if ($meeting->link)
                    <div class="crm-quick-card">
                        <div class="crm-quick-card-title">{{ __('Meeting Link') }}</div>
                        <a href="{{ $meeting->link }}" target="_blank" rel="noopener" class="btn btn-sm btn-primary w-100">
                            <i class="ti ti-external-link me-2"></i>
                            {{ __('Join Meeting') }}
                        </a>
                    </div>
                @endif

                {{-- Attachment --}}
                @if ($meeting->attachment)
                    <div class="crm-quick-card">
                        <div class="crm-quick-card-title">{{ __('Attachment') }}</div>
                        <a href="{{ route('sales-meetings.attachment', $meeting->id) }}" target="_blank"
                            class="d-flex align-items-center gap-2 text-decoration-none p-2 rounded"
                            style="background: #f8f9fa;">
                            <i class="ti ti-file-download fs-4 text-primary"></i>
                            <div class="flex-1" style="min-width: 0;">
                                <div class="small fw-medium text-truncate">{{ $meeting->attachment }}</div>
                                <div class="small text-muted">{{ __('Click to download') }}</div>
                            </div>
                        </a>
                    </div>
                @endif
            </div>

            {{-- Right: Main Content --}}
            <div class="crm-main-content">
                {{-- Products Section --}}
                <div class="crm-content-section">
                    <div class="crm-content-header">
                        <h5 class="crm-content-title">
                            <i class="ti ti-package"></i>
                            {{ __('Products') }}
                            @if ($meeting->products && $meeting->products->count() > 0)
                                <span class="crm-content-count">{{ $meeting->products->count() }}</span>
                            @endif
                        </h5>
                        @can('edit lead')
                            <a href="#" data-bs-toggle="modal" data-bs-target="#addProductModal"
                                class="btn btn-sm btn-primary" title="{{ __('Add Product') }}">
                                <i class="ti ti-plus"></i>
                            </a>
                        @endcan
                    </div>
                    <div class="crm-content-body no-padding">
                        @if ($meeting->products && $meeting->products->count() > 0)
                            <table class="crm-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Name') }}</th>
                                        <th>{{ __('SKU') }}</th>
                                        <th>{{ __('Price') }}</th>
                                        @can('edit lead')
                                            <th style="width: 80px;">{{ __('Action') }}</th>
                                        @endcan
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($meeting->products as $product)
                                        <tr>
                                            <td>{{ $product->name }}</td>
                                            <td>{{ $product->sku ?? '-' }}</td>
                                            <td>{{ \Auth::user()->priceFormat($product->sale_price) }}</td>
                                            @can('edit lead')
                                                <td>
                                                    <div class="crm-actions">
                                                        <form
                                                            action="{{ route('sales-meetings.remove-product', [$meeting->id, $product->id]) }}"
                                                            method="POST" class="d-inline">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="crm-action-btn delete bs-pass-para"
                                                                data-bs-toggle="tooltip" title="{{ __('Remove') }}">
                                                                <i class="ti ti-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            @endcan
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="crm-empty">
                                <i class="ti ti-package"></i>
                                <p>{{ __('No products yet') }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Notes Section --}}
                <div class="crm-content-section crm-notes-section">
                    <div class="crm-content-header">
                        <h5 class="crm-content-title">
                            <i class="ti ti-notes"></i>
                            {{ __('Notes') }}
                        </h5>
                    </div>
                    <div class="crm-content-body">
                        <textarea class="summernote-simple">{!! $meeting->notes !!}</textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Add Product Modal: must stay inside the section. Outside it, Blade printed it
         before <html>, ahead of the whole layout. --}}
    @can('edit lead')
        <div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel"
            aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('sales-meetings.add-product', $meeting->id) }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="addProductModalLabel">{{ __('Add Products') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                {{ Form::label('products', __('Select Products'), ['class' => 'form-label']) }}
                                <select name="products[]" id="add_products_select" class="form-control select2"
                                    multiple="multiple">
                                    @php
                                        $existingProductIds = $meeting->products->pluck('id')->toArray();
                                        $availableProducts = \App\Models\ProductService::where(
                                            'created_by',
                                            \Auth::user()->creatorId(),
                                        )
                                            ->whereNotIn('id', $existingProductIds)
                                            ->get(['id', 'name']);
                                    @endphp
                                    @foreach ($availableProducts as $product)
                                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary"
                                data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary">{{ __('Add') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan
@endsection
