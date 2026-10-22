@extends('layouts.admin')
@section('page-title')
    {{ $lead->name }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('leads.index') }}">{{ __('Lead') }}</a></li>
    <li class="breadcrumb-item">{{ $lead->name }}</li>
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
                        url: "{{ route('leads.note.store', $lead->id) }}",
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
                            response = response.responseJSON;
                            if (response.is_success) {
                                show_toastr('error', response.error, 'error');
                            } else {
                                show_toastr('error', response.error, 'error');
                            }
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
    <div class="float-end d-flex gap-2">
        @can('edit lead')
            <a href="#" data-url="{{ route('leads.edit', $lead->id) }}" data-ajax-popup="true" data-size="lg"
                data-title="{{ __('Edit Lead') }}" data-bs-toggle="tooltip" title="{{ __('Edit') }}"
                class="btn btn-sm btn-primary">
                <i class="ti ti-pencil"></i>
            </a>
        @endcan
        @if ($lead->is_converted == 1)
            <span class="badge bg-success p-2">{{ __('Converted') }}</span>
        @endif
    </div>
@endsection

@section('content')
    <div class="crm-show-page">
        {{-- Hero Header --}}
        <div class="crm-hero">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
                <div>
                    <h1 class="crm-hero-title">{{ $lead->name }}</h1>
                    <div class="crm-hero-meta">
                        @if ($lead->email)
                            <span class="crm-hero-meta-item">
                                <i class="ti ti-mail"></i>
                                <a href="https://mail.google.com/mail/?view=cm&fs=1&to={{ $lead->email }}"
                                    target="_blank">{{ $lead->email }}</a>
                            </span>
                        @endif
                        @if ($lead->phone)
                            <span class="crm-hero-meta-item">
                                <i class="ti ti-phone"></i>
                                {{ $lead->phone }}
                            </span>
                        @endif
                        @if ($lead->pipeline)
                            <span class="crm-hero-meta-item">
                                <i class="ti ti-filter"></i>
                                {{ $lead->pipeline->name }}
                            </span>
                        @endif
                        <span class="crm-hero-meta-item">
                            <i class="ti ti-calendar"></i>
                            {{ \Auth::user()->dateFormat($lead->created_at) }}
                        </span>
                    </div>
                </div>
                @if ($lead->lead_progress)
                    <div class="crm-progress" style="min-width: 200px;">
                        <div class="crm-progress-bar">
                            <div class="crm-progress-fill" style="width: {{ $lead->lead_progress }}%"></div>
                        </div>
                        <span class="crm-progress-text">{{ $lead->lead_progress }}%</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Two Column Layout --}}
        <div class="crm-layout">
            {{-- Left: Quick Panel --}}
            <div class="crm-quick-panel">
                {{-- Status Card --}}
                <div class="crm-quick-card">
                    <div class="crm-quick-card-title">{{ __('Status') }}</div>
                    <div class="crm-info-list">
                        @if ($lead->stage)
                            <div class="crm-info-item">
                                <span class="crm-info-label">{{ __('Stage') }}</span>
                                <span class="crm-info-value">{{ $lead->stage->name }}</span>
                            </div>
                        @endif
                        @if ($lead->gender)
                            <div class="crm-info-item">
                                <span class="crm-info-label">{{ __('Gender') }}</span>
                                <span class="crm-info-value">{{ $lead->gender }}</span>
                            </div>
                        @endif
                        @if ($lead->country)
                            <div class="crm-info-item">
                                <span class="crm-info-label">{{ __('Location') }}</span>
                                <span
                                    class="crm-info-value">{{ $lead->country }}{{ $lead->city ? ', ' . $lead->city : '' }}</span>
                            </div>
                        @endif
                        @if ($lead->costing_type)
                            <div class="crm-info-item">
                                <span class="crm-info-label">{{ __('Costing Type') }}</span>
                                <span class="crm-info-value">{{ $lead->costing_type }}</span>
                            </div>
                        @endif
                        @if ($lead->member_id)
                            <div class="crm-info-item">
                                <span class="crm-info-label">{{ __('Member ID') }}</span>
                                <span class="crm-info-value">{{ $lead->member_id }}</span>
                            </div>
                        @endif
                        @if ($lead->campaign_name)
                            <div class="crm-info-item">
                                <span class="crm-info-label">{{ __('Campaign') }}</span>
                                <span class="crm-info-value">{{ $lead->campaign_name }}</span>
                            </div>
                        @endif
                        @if ($lead->cost)
                            <div class="crm-info-item">
                                <span class="crm-info-label">{{ __('Cost') }}</span>
                                <span class="crm-info-value">{{ \Auth::user()->priceFormat($lead->cost) }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Contact Buttons --}}
                @if ($lead->phone || $lead->email)
                    <div class="crm-quick-card">
                        <div class="crm-quick-card-title">{{ __('Contact') }}</div>
                        <div class="crm-contact-buttons">
                            @if ($lead->phone)
                                @php
                                    $phones = array_filter(preg_split('/[,\n]+/', $lead->phone));
                                @endphp
                                @foreach ($phones as $phone)
                                    @php
                                        $cleanPhone = preg_replace('/[^0-9]/', '', trim($phone));
                                    @endphp
                                    <a href="https://wa.me/{{ $cleanPhone }}" target="_blank"
                                        class="crm-contact-btn whatsapp">
                                        <i class="ti ti-brand-whatsapp"></i>
                                        {{ trim($phone) }}
                                    </a>
                                @endforeach
                            @endif
                            @if ($lead->email)
                                <a href="https://mail.google.com/mail/?view=cm&fs=1&to={{ $lead->email }}" target="_blank"
                                    class="crm-contact-btn gmail">
                                    <i class="ti ti-mail"></i>
                                    {{ __('Email') }}
                                </a>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Assigned Users --}}
                @if ($lead->users && $lead->users->count() > 0)
                    <div class="crm-quick-card">
                        <div class="crm-quick-card-title d-flex justify-content-between align-items-center">
                            <span>{{ __('Assigned Users') }}</span>
                            @if (!Auth::user()->hasRole('Sales Employee'))
                                <a data-size="md" data-url="{{ route('leads.users.edit', $lead->id) }}"
                                    data-ajax-popup="true" data-bs-toggle="tooltip" title="{{ __('Add User') }}"
                                    class="btn btn-sm btn-primary p-1">
                                    <i class="ti ti-plus"></i>
                                </a>
                            @endif
                        </div>
                        <div class="d-flex flex-column gap-2">
                            @foreach ($lead->users as $user)
                                <div class="d-flex align-items-center justify-content-between gap-2 p-2 rounded"
                                    style="background: #f8f9fa;">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar avatar-sm"
                                            style="width: 32px; height: 32px; border-radius: 50%; background: var(--theme-color); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">
                                            {{ strtoupper(substr($user->name, 0, 1)) }}
                                        </div>
                                        <span class="small">{{ $user->name }}</span>
                                    </div>
                                    @can('edit lead')
                                        @if (!Auth::user()->hasRole('Sales Employee'))
                                            {!! Form::open([
                                                'method' => 'DELETE',
                                                'route' => ['leads.users.destroy', $lead->id, $user->id],
                                                'class' => 'd-inline',
                                            ]) !!}
                                            <button type="submit" class="btn btn-sm btn-link text-danger p-0 bs-pass-para"
                                                data-bs-toggle="tooltip" title="{{ __('Remove User') }}">
                                                <i class="ti ti-x" style="font-size: 14px;"></i>
                                            </button>
                                            {!! Form::close() !!}
                                        @endif
                                    @endcan
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Products Summary --}}
                @php $leadProducts = $lead->products(); @endphp
                @if ($leadProducts && $leadProducts->count() > 0)
                    <div class="crm-quick-card">
                        <div class="crm-quick-card-title d-flex justify-content-between align-items-center">
                            <span>{{ __('Products') }} ({{ $leadProducts->count() }})</span>
                            <a data-size="md" data-url="{{ route('leads.products.edit', $lead->id) }}"
                                data-ajax-popup="true" data-bs-toggle="tooltip" title="{{ __('Add Product') }}"
                                class="btn btn-sm btn-primary p-1">
                                <i class="ti ti-plus"></i>
                            </a>
                        </div>
                        <div class="d-flex flex-column gap-1">
                            @foreach ($leadProducts as $product)
                                <div class="d-flex justify-content-between align-items-center small p-1 rounded"
                                    style="background: #f8f9fa;">
                                    <div class="d-flex flex-column">
                                        <span>{{ $product->name }}</span>
                                        <span class="text-muted"
                                            style="font-size: 0.75rem;">{{ \Auth::user()->priceFormat($product->sale_price) }}</span>
                                    </div>
                                    @can('edit lead')
                                        {!! Form::open([
                                            'method' => 'DELETE',
                                            'route' => ['leads.products.destroy', $lead->id, $product->id],
                                            'class' => 'd-inline',
                                        ]) !!}
                                        <button type="submit" class="btn btn-sm btn-link text-danger p-0 bs-pass-para"
                                            data-bs-toggle="tooltip" title="{{ __('Remove Product') }}">
                                            <i class="ti ti-x" style="font-size: 14px;"></i>
                                        </button>
                                        {!! Form::close() !!}
                                    @endcan
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Sources --}}
                @php $leadSources = $lead->sources(); @endphp
                @if ($leadSources && count($leadSources) > 0)
                    <div class="crm-quick-card">
                        <div class="crm-quick-card-title d-flex justify-content-between align-items-center">
                            <span>{{ __('Sources') }}</span>
                            <a data-size="md" data-url="{{ route('leads.sources.edit', $lead->id) }}"
                                data-ajax-popup="true" data-bs-toggle="tooltip" title="{{ __('Add Source') }}"
                                class="btn btn-sm btn-primary p-1">
                                <i class="ti ti-plus"></i>
                            </a>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($leadSources as $source)
                                <span class="badge bg-light text-dark d-flex align-items-center gap-1"
                                    style="padding: 6px 10px;">
                                    {{ $source->name }}
                                    @can('edit lead')
                                        {!! Form::open([
                                            'method' => 'DELETE',
                                            'route' => ['leads.sources.destroy', $lead->id, $source->id],
                                            'class' => 'd-inline',
                                        ]) !!}
                                        <button type="submit" class="btn btn-sm btn-link text-danger p-0 bs-pass-para"
                                            data-bs-toggle="tooltip" title="{{ __('Remove Source') }}"
                                            style="line-height: 1;">
                                            <i class="ti ti-x" style="font-size: 12px;"></i>
                                        </button>
                                        {!! Form::close() !!}
                                    @endcan
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Created & Updated Card --}}
                <div class="crm-quick-card">
                    <div class="crm-quick-card-title">
                        <i class="ti ti-calendar-plus me-1"></i>
                        {{ __('Timeline') }}
                    </div>
                    <div class="crm-info-list">
                        <div class="crm-info-item">
                            <span class="crm-info-label">{{ __('Created') }}</span>
                            <span class="crm-info-value">
                                {{ \Auth::user()->dateFormat($lead->created_at) }}
                            </span>
                        </div>
                        <div class="crm-info-item">
                            <span class="crm-info-label">{{ __('Time Ago') }}</span>
                            <span class="crm-info-value text-muted" style="font-size: 0.9rem;">
                                {{ $lead->created_at->diffForHumans() }}
                            </span>
                        </div>
                        @if ($lead->created_at->ne($lead->updated_at))
                            <div class="crm-info-item">
                                <span class="crm-info-label">{{ __('Last Updated') }}</span>
                                <span class="crm-info-value text-muted" style="font-size: 0.85rem;">
                                    {{ \Auth::user()->dateFormat($lead->updated_at) }}
                                    <span class="d-block" style="font-size: 0.75rem; opacity: 0.7;">
                                        ({{ $lead->updated_at->diffForHumans() }})
                                    </span>
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Right: Main Content --}}
            <div class="crm-main-content">
                {{-- Deals Section --}}
                <div class="crm-content-section">
                    <div class="crm-content-header">
                        <h5 class="crm-content-title">
                            <i class="ti ti-briefcase"></i>
                            {{ __('Deals') }}
                            @if ($lead->deals->count() > 0)
                                <span class="crm-content-count">{{ $lead->deals->count() }}</span>
                            @endif
                        </h5>
                        @can('create deal')
                            <a data-size="lg" data-url="{{ route('deals.create', ['lead_id' => $lead->id]) }}"
                                data-ajax-popup="true" data-bs-toggle="tooltip" title="{{ __('Create Deal') }}"
                                class="btn btn-sm btn-primary">
                                <i class="ti ti-plus"></i>
                            </a>
                        @endcan
                    </div>
                    <div class="crm-content-body no-padding">
                        @if ($lead->deals->count() > 0)
                            <table class="crm-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Name') }}</th>
                                        <th>{{ __('Products') }}</th>
                                        <th>{{ __('Created By') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th style="width: 100px;">{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($lead->deals as $deal)
                                        <tr>
                                            <td>
                                                <a href="{{ route('deals.show', $deal->id) }}">{{ $deal->name }}</a>
                                            </td>
                                            <td>
                                                @php
                                                    $dealProducts = $deal->products();
                                                @endphp
                                                @if ($dealProducts && count($dealProducts) > 0)
                                                    @foreach ($dealProducts as $product)
                                                        <span
                                                            class="badge bg-primary p-1 px-2 rounded">{{ $product->name }}</span>
                                                    @endforeach
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @php
                                                    $dealOwner = $deal->createdByUser;
                                                    $ownerInitial = $dealOwner ? strtoupper(substr($dealOwner->name, 0, 1)) : null;
                                                    $ownerName = $dealOwner ? $dealOwner->name : null;
                                                @endphp
                                                @if ($ownerName)
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div style="width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700; flex-shrink: 0;">
                                                            {{ $ownerInitial }}
                                                        </div>
                                                        <span style="font-size: 0.85rem; font-weight: 500;">{{ $ownerName }}</span>
                                                    </div>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @php
                                                    $stageName = $deal->stage ? $deal->stage->name : null;
                                                @endphp
                                                @if ($stageName == 'Won')
                                                    <span class="crm-status success">{{ __('Won') }}</span>
                                                @elseif ($stageName == 'Loss')
                                                    <span class="crm-status"
                                                        style="background: rgba(220,53,69,0.1); color: #dc3545;">{{ __('Loss') }}</span>
                                                @else
                                                    <span class="crm-status info">{{ $stageName ?? __('Pending') }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="crm-actions">
                                                    <a href="{{ route('deals.show', $deal->id) }}"
                                                        class="crm-action-btn view" data-bs-toggle="tooltip"
                                                        title="{{ __('View') }}">
                                                        <i class="ti ti-eye"></i>
                                                    </a>
                                                    @can('edit deal')
                                                        <a href="#" data-url="{{ route('deals.edit', $deal->id) }}"
                                                            data-ajax-popup="true" data-size="lg" class="crm-action-btn edit"
                                                            data-bs-toggle="tooltip" title="{{ __('Edit') }}">
                                                            <i class="ti ti-pencil"></i>
                                                        </a>
                                                    @endcan
                                                    @can('delete deal')
                                                        @if (!Auth::user()->hasRole('Sales Employee'))
                                                            {!! Form::open(['method' => 'DELETE', 'route' => ['deals.destroy', $deal->id], 'class' => 'd-inline']) !!}
                                                            <button type="submit" class="crm-action-btn delete bs-pass-para"
                                                                data-bs-toggle="tooltip" title="{{ __('Delete') }}">
                                                                <i class="ti ti-trash"></i>
                                                            </button>
                                                            {!! Form::close() !!}
                                                        @endif
                                                    @endcan
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="crm-empty">
                                <i class="ti ti-briefcase"></i>
                                <p>{{ __('No deals yet') }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Sales Meetings Section --}}
                <div class="crm-content-section">
                    <div class="crm-content-header">
                        <h5 class="crm-content-title">
                            <i class="ti ti-calendar-event"></i>
                            {{ __('Sales Meetings') }}
                            @if ($lead->salesMeetings && $lead->salesMeetings->count() > 0)
                                <span class="crm-content-count">{{ $lead->salesMeetings->count() }}</span>
                            @endif
                        </h5>
                        @can('create lead')
                            <a data-size="lg" data-url="{{ route('sales-meetings.create', ['lead_id' => $lead->id]) }}"
                                data-ajax-popup="true" data-bs-toggle="tooltip" title="{{ __('Create Sales Meeting') }}"
                                class="btn btn-sm btn-primary">
                                <i class="ti ti-plus"></i>
                            </a>
                        @endcan
                    </div>
                    <div class="crm-content-body no-padding">
                        @if ($lead->salesMeetings && $lead->salesMeetings->count() > 0)
                            <table class="crm-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Title') }}</th>
                                        <th>{{ __('Type') }}</th>
                                        <th>{{ __('Date') }}</th>
                                        <th style="width: 100px;">{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($lead->salesMeetings as $meeting)
                                        <tr>
                                            <td>
                                                <a
                                                    href="{{ route('sales-meetings.show', $meeting->id) }}">{{ $meeting->title }}</a>
                                            </td>
                                            <td>{{ $meeting->meetingType->name ?? '-' }}</td>
                                            <td>
                                                @if ($meeting->datetime)
                                                    {{ \Auth::user()->dateFormat($meeting->datetime) }}
                                                @else
                                                    {{ \Auth::user()->dateFormat($meeting->date) }}
                                                @endif
                                            </td>
                                            <td>
                                                <div class="crm-actions">
                                                    <a href="{{ route('sales-meetings.show', $meeting->id) }}"
                                                        class="crm-action-btn view" data-bs-toggle="tooltip"
                                                        title="{{ __('View') }}">
                                                        <i class="ti ti-eye"></i>
                                                    </a>
                                                    @can('edit lead')
                                                        <a href="#"
                                                            data-url="{{ route('sales-meetings.edit', $meeting->id) }}"
                                                            data-ajax-popup="true" data-size="lg" class="crm-action-btn edit"
                                                            @if (!Auth::user()->hasRole('Sales Employee')) <a href="#"
                                                                data-url="{{ route('sales-meetings.edit', $meeting->id) }}"
                                                                data-ajax-popup="true" data-size="lg" class="crm-action-btn edit"
                                                                data-bs-toggle="tooltip" title="{{ __('Edit') }}">
                                                                <i class="ti ti-pencil"></i>
                                                            </a> @endif
                                                        @endcan
                                                        @can('delete lead')
                                                        @if (!Auth::user()->hasRole('Sales Employee'))
                                                            {!! Form::open([
                                                                'method' => 'DELETE',
                                                                'route' => ['sales-meetings.destroy', $meeting->id],
                                                                'class' => 'd-inline',
                                                            ]) !!}
                                                            <button type="submit" class="crm-action-btn delete bs-pass-para"
                                                                data-bs-toggle="tooltip" title="{{ __('Delete') }}">
                                                                <i class="ti ti-trash"></i>
                                                            </button>
                                                            {!! Form::close() !!}
                                                        @endif
                                                    @endcan
                                                        </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="crm-empty">
                                <i class="ti ti-calendar-event"></i>
                                <p>{{ __('No meetings yet') }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Deal Purchases Section --}}
                @php
                    $leadDealPurchases = $lead->dealPurchases()->get();
                    // Check if any deal has Won status
                    $hasWonDeal = false;
                    foreach ($lead->deals as $deal) {
                        if ($deal->stage && strtolower($deal->stage->name) == 'won') {
                            $hasWonDeal = true;
                            break;
                        }
                    }
                @endphp
                @if ($hasWonDeal)
                    <div class="crm-content-section">
                        <div class="crm-content-header">
                            <h5 class="crm-content-title">
                                <i class="ti ti-shopping-cart"></i>
                                {{ __('Deal Purchases') }}
                                @if ($leadDealPurchases && $leadDealPurchases->count() > 0)
                                    <span class="crm-content-count">{{ $leadDealPurchases->count() }}</span>
                                @endif
                            </h5>
                            @can('create deal')
                                <a href="{{ route('deal-purchase.create', ['lead_id' => $lead->id]) }}"
                                    class="btn btn-sm btn-primary">
                                    <i class="ti ti-plus"></i>
                                </a>
                            @endcan
                        </div>
                        <div class="crm-content-body no-padding">
                            @if ($leadDealPurchases && $leadDealPurchases->count() > 0)
                                <table class="crm-table">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Deal') }}</th>
                                            <th>{{ __('Price') }}</th>
                                            <th>{{ __('Paid') }}</th>
                                            <th>{{ __('Unpaid') }}</th>
                                            <th>{{ __('Status') }}</th>
                                            <th>{{ __('Date') }}</th>
                                            <th style="width: 100px;">{{ __('Action') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($leadDealPurchases as $purchase)
                                            @php
                                                $paidInstallments = $purchase->installments
                                                    ->where('status', 'paid')
                                                    ->sum('amount');
                                                $unpaidInstallments = $purchase->installments
                                                    ->where('status', 'unpaid')
                                                    ->sum('amount');
                                                $totalPaid = $purchase->paid_amount + $paidInstallments;
                                                $totalUnpaid = $purchase->price - $totalPaid;
                                            @endphp
                                            <tr>
                                                <td>
                                                    <a href="{{ route('deal-purchase.show', $purchase->id) }}">
                                                        {{ $purchase->deal->name ?? '#' . $purchase->id }}
                                                    </a>
                                                </td>
                                                <td>{{ $purchase->currency }} {{ number_format($purchase->price, 2) }}
                                                </td>
                                                <td>
                                                    <span class="badge bg-success">
                                                        {{ $purchase->currency }} {{ number_format($totalPaid, 2) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-warning">
                                                        {{ $purchase->currency }} {{ number_format($totalUnpaid, 2) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    @if ($purchase->status == 'complete')
                                                        <span class="crm-status success">{{ __('Complete') }}</span>
                                                    @else
                                                        <span class="crm-status info">{{ __('Pending') }}</span>
                                                    @endif
                                                </td>
                                                <td>{{ \Auth::user()->dateFormat($purchase->purchase_date) }}</td>
                                                <td>
                                                    <div class="crm-actions">
                                                        <a href="{{ route('deal-purchase.show', $purchase->id) }}"
                                                            class="crm-action-btn view" data-bs-toggle="tooltip"
                                                            title="{{ __('View') }}">
                                                            <i class="ti ti-eye"></i>
                                                        </a>
                                                        @can('edit deal')
                                                            <a href="{{ route('deal-purchase.edit', $purchase->id) }}?lead_id={{ $lead->id }}"
                                                                class="crm-action-btn edit" data-bs-toggle="tooltip"
                                                                title="{{ __('Edit') }}">
                                                                <i class="ti ti-pencil"></i>
                                                            </a>
                                                        @endcan
                                                        @can('delete deal')
                                                            @if (!Auth::user()->hasRole('Sales Employee'))
                                                                {!! Form::open([
                                                                    'method' => 'DELETE',
                                                                    'route' => ['deal-purchase.destroy', $purchase->id],
                                                                    'class' => 'd-inline',
                                                                ]) !!}
                                                                <input type="hidden" name="lead_id"
                                                                    value="{{ $lead->id }}">
                                                                <button type="submit"
                                                                    class="crm-action-btn delete bs-pass-para"
                                                                    data-bs-toggle="tooltip" title="{{ __('Delete') }}">
                                                                    <i class="ti ti-trash"></i>
                                                                </button>
                                                                {!! Form::close() !!}
                                                            @endif
                                                        @endcan
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <div class="crm-empty">
                                    <i class="ti ti-shopping-cart"></i>
                                    <p>{{ __('No purchases yet') }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Installments Section --}}
                @if ($hasWonDeal)
                    @php $leadInstallments = $lead->installments()->get(); @endphp
                    <div class="crm-content-section">
                        <div class="crm-content-header">
                            <h5 class="crm-content-title">
                                <i class="ti ti-cash"></i>
                                {{ __('Installments') }}
                                @if ($leadInstallments && $leadInstallments->count() > 0)
                                    <span class="crm-content-count">{{ $leadInstallments->count() }}</span>
                                @endif
                            </h5>
                            @can('create deal')
                                <a href="{{ route('installments.create') }}?lead_id={{ $lead->id }}"
                                    class="btn btn-sm btn-primary" data-bs-toggle="tooltip"
                                    title="{{ __('Add Installment') }}">
                                    <i class="ti ti-plus"></i>
                                </a>
                            @endcan
                        </div>
                        <div class="crm-content-body no-padding">
                            @if ($leadInstallments && $leadInstallments->count() > 0)
                                <table class="crm-table">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Amount') }}</th>
                                            <th>{{ __('Status') }}</th>
                                            <th>{{ __('Date') }}</th>
                                            <th style="width: 100px;">{{ __('Action') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($leadInstallments as $installment)
                                            <tr>
                                                <td>{{ \Auth::user()->priceFormat($installment->amount) }}</td>
                                                <td>
                                                    @if ($installment->status == 'paid')
                                                        <span class="crm-status success">{{ __('Paid') }}</span>
                                                    @else
                                                        <span class="crm-status warning">{{ __('Unpaid') }}</span>
                                                    @endif
                                                </td>
                                                <td>{{ \Auth::user()->dateFormat($installment->date) }}</td>
                                                <td>
                                                    <div class="crm-actions">
                                                        <a href="{{ route('installments.show', $installment->id) }}"
                                                            class="crm-action-btn view" data-bs-toggle="tooltip"
                                                            title="{{ __('View') }}">
                                                            <i class="ti ti-eye"></i>
                                                        </a>
                                                        @can('edit deal')
                                                            <a href="{{ route('installments.edit', $installment->id) }}?lead_id={{ $lead->id }}"
                                                                class="crm-action-btn edit" data-bs-toggle="tooltip"
                                                                title="{{ __('Edit') }}">
                                                                <i class="ti ti-pencil"></i>
                                                            </a>
                                                        @endcan
                                                        @can('delete deal')
                                                            @if (!Auth::user()->hasRole('Sales Employee'))
                                                                {!! Form::open([
                                                                    'method' => 'DELETE',
                                                                    'route' => ['installments.destroy', $installment->id],
                                                                    'class' => 'd-inline',
                                                                ]) !!}
                                                                <input type="hidden" name="lead_id"
                                                                    value="{{ $lead->id }}">
                                                                <button type="submit"
                                                                    class="crm-action-btn delete bs-pass-para"
                                                                    data-bs-toggle="tooltip" title="{{ __('Delete') }}">
                                                                    <i class="ti ti-trash"></i>
                                                                </button>
                                                                {!! Form::close() !!}
                                                            @endif
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
                @endif

                {{-- Notes Section --}}
                <div class="crm-content-section crm-notes-section">
                    <div class="crm-content-header">
                        <h5 class="crm-content-title">
                            <i class="ti ti-notes"></i>
                            {{ __('Notes') }}
                        </h5>
                    </div>
                    <div class="crm-content-body">
                        <textarea class="summernote-simple" name="note">{!! $lead->notes !!}</textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
