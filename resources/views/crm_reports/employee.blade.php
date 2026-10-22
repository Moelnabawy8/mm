@extends('layouts.admin')

@php
    $setting = \App\Models\Utility::settings();
    $isDark = ($setting['cust_darklayout'] ?? '') === 'on';
    $isRtl = in_array(app()->getLocale(), ['ar', 'he'], true) || ($setting['SITE_RTL'] ?? '') === 'on';
    $selectedEmployees = array_map('intval', (array) request('employees', []));
    $tabIcons = [
        'leads' => 'ti-users', 'actions' => 'ti-hand-click', 'stages' => 'ti-layout-kanban', 'neglected' => 'ti-alert-triangle',
        'communications' => 'ti-list-details', 'meetings' => 'ti-calendar-event', 'comparison' => 'ti-trophy', 'detailed' => 'ti-table',
    ];
@endphp

@section('page-title')
    {{ __('Employee Reports') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('CRM Reports') }}</li>
    <li class="breadcrumb-item">{{ __('Employee Reports') }}</li>
@endsection

@include('crm_reports._styles')

@section('content')
<div class="crm-reports {{ $isDark ? 'crm-dark' : '' }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}"
     data-active="{{ $active }}"
     data-query="{{ http_build_query($ctx->query()) }}"
     data-section-url="{{ route('crm-reports.employee.section', '__SECTION__', false) }}"
     data-empty="{{ __('No data for these filters') }}"
     data-error="{{ __('Could not load this report. Try again.') }}">

    @include('crm_reports._nav', ['current' => 'employee'])

    {{-- Filter bar: scopes every KPI and every report below it. --}}
    <div class="card crm-filters d-print-none">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('crm-reports.employee') }}" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="{{ $active }}">
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <label class="form-label" for="crm-period">{{ __('Period') }}</label>
                    <select name="period" id="crm-period" class="form-select">
                        @foreach ($options['periods'] as $key => $label)
                            <option value="{{ $key }}" @selected($ctx->preset === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6 crm-custom-range d-none">
                    <label class="form-label" for="crm-from">{{ __('From') }}</label>
                    <input type="date" name="from" id="crm-from" class="form-control" value="{{ $ctx->from->toDateString() }}">
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6 crm-custom-range d-none">
                    <label class="form-label" for="crm-to">{{ __('To') }}</label>
                    <input type="date" name="to" id="crm-to" class="form-control" value="{{ $ctx->to->toDateString() }}">
                </div>
                @unless ($ctx->isSelfOnly())
                    <div class="col-xl-3 col-md-6">
                        <label class="form-label" for="crm-employees">{{ __('Employees') }}</label>
                        <select name="employees[]" id="crm-employees" class="form-control select2" multiple
                                data-placeholder="{{ __('All employees') }}">
                            @foreach ($options['employees'] as $id => $name)
                                <option value="{{ $id }}" @selected(in_array((int) $id, $selectedEmployees, true))>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endunless
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <label class="form-label" for="crm-pipeline">{{ __('Pipeline') }}</label>
                    <select name="pipeline" id="crm-pipeline" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($options['pipelines'] as $id => $name)
                            <option value="{{ $id }}" @selected($ctx->pipelineId === (int) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <label class="form-label" for="crm-source">{{ __('Source') }}</label>
                    <select name="source" id="crm-source" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($options['sources'] as $id => $name)
                            <option value="{{ $id }}" @selected($ctx->sourceId === (int) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <label class="form-label" for="crm-campaign">{{ __('Campaign') }}</label>
                    <select name="campaign" id="crm-campaign" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($options['campaigns'] as $name)
                            <option value="{{ $name }}" @selected($ctx->campaign === $name)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <label class="form-label" for="crm-country">{{ __('Country') }}</label>
                    <select name="country" id="crm-country" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($options['countries'] as $name)
                            <option value="{{ $name }}" @selected($ctx->country === $name)>{{ __($name) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="ti ti-filter"></i> {{ __('Apply') }}</button>
                    <a href="{{ route('crm-reports.employee', ['tab' => $active]) }}" class="btn btn-light" title="{{ __('Reset') }}"
                       data-bs-toggle="tooltip"><i class="ti ti-refresh"></i></a>
                </div>
            </form>
            <div class="crm-filter-summary mt-2">
                <i class="ti ti-calendar"></i>
                {{ __('Leads created') }}: <strong>{{ $ctx->preset === 'all' ? __('All time') : $ctx->from->format('Y-m-d') . ' → ' . $ctx->to->format('Y-m-d') }}</strong>
                @if ($ctx->hasComparison())
                    <span class="text-muted">· {{ __('compared with') }} {{ $ctx->prevFrom->format('Y-m-d') }} → {{ $ctx->prevTo->format('Y-m-d') }}</span>
                @endif
                @if ($ctx->isSelfOnly())
                    <span class="badge bg-light text-dark ms-1"><i class="ti ti-lock"></i> {{ __('Showing your own numbers') }}</span>
                @elseif (!$ctx->seesEveryone())
                    <span class="badge bg-light text-dark ms-1"><i class="ti ti-users"></i> {{ __('Showing your sales group') }}</span>
                @endif
            </div>
        </div>
    </div>

    <div class="crm-print-head">
        <h3>{{ __('Employee Reports') }}</h3>
        <div>{{ __('Leads created') }}: {{ $ctx->from->format('Y-m-d') }} → {{ $ctx->to->format('Y-m-d') }} · {{ __('Printed') }} {{ now()->format('Y-m-d H:i') }}</div>
    </div>

    {{-- KPI strip: the headline numbers for the window, with change against the previous one. --}}
    <div class="row g-3 mb-3 crm-kpis">
        @foreach ($kpis as $kpi)
            <div class="col-xl-3 col-md-6 col-6">
                <div class="card crm-kpi mb-0">
                    <div class="card-body">
                        <div class="crm-kpi-label"><i class="ti {{ $kpi['icon'] }}"></i>{{ $kpi['label'] }}</div>
                        <div class="crm-kpi-value">
                            {{ $kpi['format'] === 'pct' ? number_format($kpi['value'], 1) : number_format($kpi['value']) }}{{ $kpi['suffix'] }}
                        </div>
                        @if ($ctx->hasComparison())
                            @php $d = $kpi['delta']; @endphp
                            <div class="crm-kpi-delta {{ $d === null ? 'flat' : ($d > 0 ? 'up' : ($d < 0 ? 'down' : 'flat')) }}">
                                @if ($d === null)
                                    {{ __('New this period') }}
                                @else
                                    <i class="ti {{ $d > 0 ? 'ti-arrow-up-right' : ($d < 0 ? 'ti-arrow-down-right' : 'ti-minus') }}"></i>
                                    {{ abs($d) }}% <span class="text-muted fw-normal">{{ __('vs previous') }}</span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-body">
            <div class="crm-tabs" role="tablist" aria-label="{{ __('Employee Reports') }}">
                @foreach ($sections as $key => $title)
                    <button type="button" class="crm-tab {{ $key === $active ? 'active' : '' }}" role="tab"
                            data-section="{{ $key }}" aria-selected="{{ $key === $active ? 'true' : 'false' }}" aria-controls="crm-section">
                        <span class="crm-tab-num">{{ $loop->iteration }}</span>
                        <i class="ti {{ $tabIcons[$key] ?? 'ti-chart-bar' }}"></i>{{ __($title) }}
                    </button>
                @endforeach
            </div>
            <div class="crm-section" id="crm-section" role="tabpanel" aria-live="polite">
                <div class="crm-loader"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">{{ __('Loading') }}</span></div></div>
                <div class="crm-section-body"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@include('crm_reports._scripts')
