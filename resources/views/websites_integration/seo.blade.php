@extends('layouts.admin')

@section('page-title')
    {{ __('Websites — SEO') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('websites.dashboard') }}">{{ __('Websites') }}</a></li>
    <li class="breadcrumb-item">{{ __('SEO') }}</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="alert alert-info"><i class="ti ti-info-circle me-1"></i> {{ __('Placeholder — Google Search Console data will populate this view once a Service Account is connected.') }}</div>
        </div>

        @php
            $kpis = [
                ['label' => __('Total Clicks (28d)'),       'value' => '—', 'icon' => 'click',         'color' => 'primary'],
                ['label' => __('Impressions (28d)'),         'value' => '—', 'icon' => 'eye',           'color' => 'info'],
                ['label' => __('Avg CTR'),                   'value' => '—', 'icon' => 'percentage',    'color' => 'warning'],
                ['label' => __('Avg Position'),              'value' => '—', 'icon' => 'arrows-sort',   'color' => 'success'],
            ];
        @endphp
        @foreach ($kpis as $k)
            <div class="col-xl-3 col-md-6">
                <div class="card"><div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-1">{{ $k['label'] }}</p>
                            <h3 class="mb-0">{{ $k['value'] }}</h3>
                        </div>
                        <span class="avtar avtar-l bg-light-{{ $k['color'] }}"><i class="ti ti-{{ $k['icon'] }} fs-3"></i></span>
                    </div>
                </div></div>
            </div>
        @endforeach

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">{{ __('Top Search Queries') }}</h5></div>
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table">
                            <thead><tr>
                                <th>{{ __('Query') }}</th>
                                <th>{{ __('Clicks') }}</th>
                                <th>{{ __('Impressions') }}</th>
                                <th>{{ __('CTR') }}</th>
                                <th>{{ __('Position') }}</th>
                            </tr></thead>
                            <tbody>
                                <tr><td colspan="5" class="text-center text-muted py-5">
                                    <i class="ti ti-search-off fs-1 d-block mb-2 opacity-50"></i>
                                    {{ __('Connect Search Console API to view top queries.') }}
                                </td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">{{ __('Indexing Status') }}</h5></div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="d-flex justify-content-between border-bottom py-2"><span>{{ __('Indexed pages') }}</span><strong>—</strong></li>
                        <li class="d-flex justify-content-between border-bottom py-2"><span>{{ __('Excluded pages') }}</span><strong>—</strong></li>
                        <li class="d-flex justify-content-between border-bottom py-2"><span>{{ __('Errors') }}</span><strong class="text-danger">—</strong></li>
                        <li class="d-flex justify-content-between py-2"><span>{{ __('Sitemap submitted') }}</span><strong>—</strong></li>
                    </ul>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="mb-0">{{ __('Top Landing Pages') }}</h5></div>
                <div class="card-body">
                    <p class="text-muted mb-0 small">{{ __('Pages by clicks will appear here.') }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection
