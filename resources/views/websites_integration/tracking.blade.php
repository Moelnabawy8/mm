@extends('layouts.admin')

@section('page-title')
    {{ __('Websites — Tracking') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('websites.dashboard') }}">{{ __('Websites') }}</a></li>
    <li class="breadcrumb-item">{{ __('Tracking') }}</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="alert alert-info"><i class="ti ti-info-circle me-1"></i> {{ __('Placeholder — Live users and analytics from GA4 will appear here once connected.') }}</div>
        </div>

        @php
            $live = [
                ['label' => __('Active Users (now)'), 'value' => '—', 'icon' => 'user-bolt',     'color' => 'success'],
                ['label' => __('Sessions Today'),     'value' => '—', 'icon' => 'activity',      'color' => 'primary'],
                ['label' => __('Pageviews Today'),    'value' => '—', 'icon' => 'eye',           'color' => 'info'],
                ['label' => __('Avg Session Time'),   'value' => '—', 'icon' => 'clock',         'color' => 'warning'],
            ];
        @endphp
        @foreach ($live as $l)
            <div class="col-xl-3 col-md-6">
                <div class="card"><div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-1">{{ $l['label'] }}</p>
                            <h3 class="mb-0">{{ $l['value'] }}</h3>
                        </div>
                        <span class="avtar avtar-l bg-light-{{ $l['color'] }}"><i class="ti ti-{{ $l['icon'] }} fs-3"></i></span>
                    </div>
                </div></div>
            </div>
        @endforeach

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">{{ __('Real-Time Visitors by Page') }}</h5></div>
                <div class="card-body">
                    <div class="text-center text-muted py-5">
                        <i class="ti ti-radar fs-1 opacity-50 d-block mb-2"></i>
                        {{ __('Live data stream will appear here.') }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">{{ __('Top Sources') }}</h5></div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="d-flex justify-content-between border-bottom py-2"><span>Google</span><strong>—</strong></li>
                        <li class="d-flex justify-content-between border-bottom py-2"><span>Direct</span><strong>—</strong></li>
                        <li class="d-flex justify-content-between border-bottom py-2"><span>Social</span><strong>—</strong></li>
                        <li class="d-flex justify-content-between py-2"><span>Referral</span><strong>—</strong></li>
                    </ul>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="mb-0">{{ __('Devices') }}</h5></div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="d-flex justify-content-between border-bottom py-2"><span>{{ __('Desktop') }}</span><strong>—</strong></li>
                        <li class="d-flex justify-content-between border-bottom py-2"><span>{{ __('Mobile') }}</span><strong>—</strong></li>
                        <li class="d-flex justify-content-between py-2"><span>{{ __('Tablet') }}</span><strong>—</strong></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
