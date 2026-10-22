@extends('layouts.admin')
@section('page-title')
    {{ __('Installment Details') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('installments.index') }}">{{ __('Installments') }}</a></li>
    <li class="breadcrumb-item">#{{ $installment->id }}</li>
@endsection

@section('action-btn')
    <div class="float-end">
        @can('edit deal')
            <a href="{{ route('installments.edit', $installment->id) }}" class="btn btn-sm btn-primary" data-bs-toggle="tooltip"
                title="{{ __('Edit') }}">
                <i class="ti ti-pencil"></i>
            </a>
        @endcan
    </div>
@endsection

@section('content')
    <div class="crm-show-page">
        {{-- Hero Header --}}
        <div class="crm-hero bg-gradient-primary text-white p-4 rounded shadow-sm mb-4">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
                <div>
                    <h1 class="crm-hero-title display-4 fw-bold mb-2">{{ \Auth::user()->priceFormat($installment->amount) }}
                    </h1>
                    <div class="crm-hero-meta">
                        <span class="crm-hero-meta-item me-3">
                            <i class="ti ti-calendar me-1"></i>
                            {{ \Auth::user()->dateFormat($installment->date) }}
                        </span>
                        @if ($installment->dealPurchase && $installment->dealPurchase->deal)
                            <span class="crm-hero-meta-item me-3">
                                <i class="ti ti-briefcase me-1"></i>
                                <a href="{{ route('deals.show', $installment->dealPurchase->deal->id) }}"
                                    class="text-white text-decoration-underline">
                                    {{ $installment->dealPurchase->deal->name }}
                                </a>
                            </span>
                        @endif
                        @if ($installment->dealPurchase && $installment->dealPurchase->deal && $installment->dealPurchase->deal->lead)
                            <span class="crm-hero-meta-item">
                                <i class="ti ti-user me-1"></i>
                                <a href="{{ route('leads.show', $installment->dealPurchase->deal->lead->id) }}"
                                    class="text-white text-decoration-underline">
                                    {{ $installment->dealPurchase->deal->lead->name }}
                                </a>
                            </span>
                        @endif
                    </div>
                </div>
                @if ($installment->status == 'paid')
                    <span class="badge bg-success fs-5 px-3 py-2"><i class="ti ti-check me-1"></i>
                        {{ __('Paid') }}</span>
                @else
                    <span class="badge bg-warning fs-5 px-3 py-2"><i class="ti ti-clock me-1"></i>
                        {{ __('Unpaid') }}</span>
                @endif
            </div>
        </div>

        {{-- Two Column Layout --}}
        <div class="crm-layout">
            {{-- Left: Quick Panel --}}
            <div class="crm-quick-panel">
                {{-- Installment Info --}}
                <div class="crm-quick-card">
                    <div class="crm-quick-card-title">{{ __('Installment Details') }}</div>
                    <div class="crm-info-list">
                        <div class="crm-info-item">
                            <span class="crm-info-label">{{ __('Amount') }}</span>
                            <span
                                class="crm-info-value text-primary fs-5 fw-bold">{{ \Auth::user()->priceFormat($installment->amount) }}</span>
                        </div>
                        <div class="crm-info-item">
                            <span class="crm-info-label">{{ __('Exemption') }}</span>
                            <span class="crm-info-value">
                                @php
                                    $exemption = $installment->exemption ?? 0;
                                @endphp
                                @if ($exemption > 0)
                                    <span class="badge bg-info fs-6">{{ \Auth::user()->priceFormat($exemption) }}</span>
                                @else
                                    <span class="text-muted">{{ \Auth::user()->priceFormat(0) }}</span>
                                @endif
                            </span>
                        </div>
                        <div class="crm-info-item">
                            <span class="crm-info-label">{{ __('Effective Amount') }}</span>
                            <span class="crm-info-value text-success fs-5 fw-bold">
                                @php
                                    $effectiveAmount = $installment->amount - ($installment->exemption ?? 0);
                                @endphp
                                {{ \Auth::user()->priceFormat($effectiveAmount) }}
                            </span>
                        </div>
                        <div class="crm-info-item">
                            <span class="crm-info-label">{{ __('Status') }}</span>
                            <span class="crm-info-value">
                                @if ($installment->status == 'paid')
                                    <span class="badge bg-success">{{ __('Paid') }}</span>
                                @else
                                    <span class="badge bg-warning">{{ __('Unpaid') }}</span>
                                @endif
                            </span>
                        </div>
                        <div class="crm-info-item">
                            <span class="crm-info-label">{{ __('Hopeless Status') }}</span>
                            <span class="crm-info-value">
                                @if ($installment->is_hopeless)
                                    <span class="badge bg-danger"><i class="ti ti-alert-triangle"></i>
                                        {{ __('ON') }}</span>
                                @else
                                    <span class="badge bg-secondary"><i class="ti ti-circle-check"></i>
                                        {{ __('OFF') }}</span>
                                @endif
                            </span>
                        </div>
                        <div class="crm-info-item">
                            <span class="crm-info-label">{{ __('Date') }}</span>
                            <span class="crm-info-value">{{ \Auth::user()->dateFormat($installment->date) }}</span>
                        </div>
                        <div class="crm-info-item">
                            <span class="crm-info-label">{{ __('Created') }}</span>
                            <span class="crm-info-value">{{ \Auth::user()->dateFormat($installment->created_at) }}</span>
                        </div>
                    </div>
                </div>

                {{-- Description --}}
                @if ($installment->description)
                    <div class="crm-quick-card">
                        <div class="crm-quick-card-title">{{ __('Description') }}</div>
                        <p class="mb-0 small text-muted">{{ $installment->description }}</p>
                    </div>
                @endif
            </div>

            {{-- Right: Main Content --}}
            <div class="crm-main-content">
                {{-- Financial Summary Cards --}}
                <div class="crm-content-section mb-4">
                    <div class="crm-content-header">
                        <h5 class="crm-content-title">
                            <i class="ti ti-calculator"></i>
                            {{ __('Financial Summary') }}
                        </h5>
                    </div>
                    <div class="crm-content-body">
                        <div class="row g-3">
                            {{-- Original Amount Card --}}
                            <div class="col-md-4">
                                <div class="card border-0 shadow-sm h-100"
                                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0 text-white">{{ __('Original Amount') }}</h6>
                                            <i class="ti ti-currency-dollar fs-3 text-white"></i>
                                        </div>
                                        <h3 class="mb-0 text-white fw-bold">
                                            {{ \Auth::user()->priceFormat($installment->amount) }}</h3>
                                    </div>
                                </div>
                            </div>

                            {{-- Exemption Card --}}
                            <div class="col-md-4">
                                <div class="card border-0 shadow-sm h-100" style="background-color: #d1ecf1;">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0 text-info">{{ __('Exemption') }}</h6>
                                            <i class="ti ti-discount-2 fs-3 text-info"></i>
                                        </div>
                                        <h3 class="mb-0 text-info fw-bold">
                                            @php
                                                $exemption = $installment->exemption ?? 0;
                                            @endphp
                                            {{ \Auth::user()->priceFormat($exemption) }}
                                        </h3>
                                        @if ($exemption > 0)
                                            <small class="text-info">
                                                <i class="ti ti-gift"></i> {{ __('Forgiven Amount') }}
                                            </small>
                                        @else
                                            <small class="text-muted">{{ __('No exemption') }}</small>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- Effective Amount Card --}}
                            <div class="col-md-4">
                                <div class="card border-0 shadow-sm h-100" style="background-color: #d4edda;">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0 text-success">{{ __('Effective Amount') }}</h6>
                                            <i class="ti ti-calculator fs-3 text-success"></i>
                                        </div>
                                        <h3 class="mb-0 text-success fw-bold">
                                            @php
                                                $effectiveAmount =
                                                    $installment->amount - ($installment->exemption ?? 0);
                                            @endphp
                                            {{ \Auth::user()->priceFormat($effectiveAmount) }}
                                        </h3>
                                        <small class="text-success">
                                            <i class="ti ti-check"></i> {{ __('Payable Amount') }}
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Hopeless Warning --}}
                        @if ($installment->is_hopeless)
                            <div class="alert alert-danger mt-3 mb-0">
                                <div class="d-flex align-items-center">
                                    <i class="ti ti-alert-triangle fs-3 me-3"></i>
                                    <div>
                                        <h6 class="mb-1">{{ __('Hopeless Installment') }}</h6>
                                        <p class="mb-0 small">
                                            {{ __('This installment is marked as uncollectable. The customer cannot pay this amount.') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Related Information --}}
                <div class="crm-content-section">
                    <div class="crm-content-header">
                        <h5 class="crm-content-title">
                            <i class="ti ti-link"></i>
                            {{ __('Related Information') }}
                        </h5>
                    </div>
                    <div class="crm-content-body">
                        <div class="row g-3">
                            @if ($installment->dealPurchase)
                                <div class="col-md-4">
                                    <div class="p-3 rounded" style="background: #f8f9fa;">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded p-2" style="background: var(--theme-color);">
                                                <i class="ti ti-shopping-cart text-white"></i>
                                            </div>
                                            <div>
                                                <div class="small text-muted">{{ __('Deal Purchase') }}</div>
                                                <div class="fw-medium">
                                                    <a
                                                        href="{{ route('deal-purchase.show', $installment->dealPurchase->id) }}">
                                                        {{ $installment->dealPurchase->currency }}
                                                        {{ number_format($installment->dealPurchase->price, 2) }}
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            @if ($installment->dealPurchase && $installment->dealPurchase->deal)
                                <div class="col-md-4">
                                    <div class="p-3 rounded" style="background: #f8f9fa;">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded p-2" style="background: var(--theme-color);">
                                                <i class="ti ti-briefcase text-white"></i>
                                            </div>
                                            <div>
                                                <div class="small text-muted">{{ __('Deal') }}</div>
                                                <div class="fw-medium">
                                                    <a
                                                        href="{{ route('deals.show', $installment->dealPurchase->deal->id) }}">
                                                        {{ $installment->dealPurchase->deal->name }}
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            @if ($installment->dealPurchase && $installment->dealPurchase->deal && $installment->dealPurchase->deal->lead)
                                <div class="col-md-4">
                                    <div class="p-3 rounded" style="background: #f8f9fa;">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded p-2" style="background: var(--theme-color);">
                                                <i class="ti ti-user text-white"></i>
                                            </div>
                                            <div>
                                                <div class="small text-muted">{{ __('Lead') }}</div>
                                                <div class="fw-medium">
                                                    <a
                                                        href="{{ route('leads.show', $installment->dealPurchase->deal->lead->id) }}">
                                                        {{ $installment->dealPurchase->deal->lead->name }}
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
