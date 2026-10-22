@extends('layouts.admin')

@section('page-title')
    {{ __('Marketing Dashboard') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Marketing') }}</li>
@endsection

@section('action-btn')
    <a href="{{ route('marketing.accounts.create') }}" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="{{ __('Connect Account') }}">
        <i class="ti ti-plus"></i> {{ __('Connect Account') }}
    </a>
    <a href="{{ route('marketing.campaigns.create') }}" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="{{ __('New Campaign') }}">
        <i class="ti ti-plus"></i> {{ __('New Campaign') }}
    </a>
@endsection

@push('theme-script')
    <script src="{{ asset('assets/libs/apexcharts/dist/apexcharts.min.js') }}"></script>
@endpush

@section('content')
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-8">
                            <h6 class="text-muted mb-1">{{ __('Connected Accounts') }}</h6>
                            <h3 class="mb-0">{{ $totalAccounts }}</h3>
                        </div>
                        <div class="col-4 text-end">
                            <i class="ti ti-share text-primary" style="font-size: 2.5rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-8">
                            <h6 class="text-muted mb-1">{{ __('Active Campaigns') }}</h6>
                            <h3 class="mb-0">{{ $activeCampaigns }}</h3>
                        </div>
                        <div class="col-4 text-end">
                            <i class="ti ti-speakerphone text-success" style="font-size: 2.5rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-8">
                            <h6 class="text-muted mb-1">{{ __('Total Spent') }}</h6>
                            <h3 class="mb-0">${{ number_format($totalSpent, 2) }}</h3>
                        </div>
                        <div class="col-4 text-end">
                            <i class="ti ti-currency-dollar text-warning" style="font-size: 2.5rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-8">
                            <h6 class="text-muted mb-1">{{ __('Total Impressions') }}</h6>
                            <h3 class="mb-0">{{ number_format($totalImpressions) }}</h3>
                        </div>
                        <div class="col-4 text-end">
                            <i class="ti ti-eye text-info" style="font-size: 2.5rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <h5>{{ __('Campaign Performance') }}</h5>
                </div>
                <div class="card-body">
                    <div id="performance-chart"></div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card">
                <div class="card-header">
                    <h5>{{ __('Connected Accounts') }}</h5>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    @forelse($accounts as $account)
                        <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                            <div class="me-3">
                                <i class="{{ $account->platform_icon }}" style="font-size: 2rem; color: {{ $account->platform_color }};"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0">{{ $account->account_name }}</h6>
                                <small class="text-muted">{{ ucfirst($account->platform) }}</small>
                            </div>
                            <div>
                                <span class="badge bg-{{ $account->status == 'active' ? 'success' : 'danger' }}">
                                    {{ ucfirst($account->status) }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4">
                            <i class="ti ti-share text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2">{{ __('No accounts connected yet') }}</p>
                            <a href="{{ route('marketing.accounts.create') }}" class="btn btn-sm btn-primary">
                                {{ __('Connect Account') }}
                            </a>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5>{{ __('Recent Campaigns') }}</h5>
                        <a href="{{ route('marketing.campaigns') }}" class="btn btn-sm btn-primary">
                            {{ __('View All') }}
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>{{ __('Campaign Name') }}</th>
                                    <th>{{ __('Platform') }}</th>
                                    <th>{{ __('Objective') }}</th>
                                    <th>{{ __('Budget') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Performance') }}</th>
                                    <th class="text-end">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($campaigns as $campaign)
                                    <tr>
                                        <td>
                                            <a href="{{ route('marketing.campaigns.show', $campaign->id) }}" class="text-dark">
                                                <strong>{{ $campaign->campaign_name }}</strong>
                                            </a>
                                        </td>
                                        <td>
                                            <i class="{{ $campaign->account->platform_icon }}" style="color: {{ $campaign->account->platform_color }};"></i>
                                            {{ ucfirst($campaign->platform) }}
                                        </td>
                                        <td>
                                            <span class="badge bg-info">{{ ucfirst($campaign->objective) }}</span>
                                        </td>
                                        <td>${{ number_format($campaign->budget, 2) }}</td>
                                        <td>
                                            <span class="badge bg-{{ $campaign->status == 'active' ? 'success' : ($campaign->status == 'paused' ? 'warning' : 'secondary') }}">
                                                {{ ucfirst($campaign->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                {{ number_format($campaign->total_impressions) }} impressions<br>
                                                {{ number_format($campaign->total_clicks) }} clicks
                                            </small>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('marketing.campaigns.show', $campaign->id) }}" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="{{ __('View') }}">
                                                <i class="ti ti-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="ti ti-speakerphone text-muted" style="font-size: 3rem;"></i>
                                            <p class="text-muted mt-2">{{ __('No campaigns created yet') }}</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script-page')
<script>
    (function() {
        var options = {
            series: [{
                name: '{{ __("Impressions") }}',
                data: {!! json_encode(array_values($chartData['impressions'])) !!}
            }, {
                name: '{{ __("Clicks") }}',
                data: {!! json_encode(array_values($chartData['clicks'])) !!}
            }, {
                name: '{{ __("Spend") }}',
                data: {!! json_encode(array_values($chartData['spend'])) !!}
            }],
            chart: {
                height: 350,
                type: 'area',
                toolbar: {
                    show: false
                }
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                curve: 'smooth',
                width: 2
            },
            xaxis: {
                categories: {!! json_encode(array_values($chartData['labels'])) !!}
            },
            colors: ['#6fd943', '#ffa21d', '#ff3a6e'],
            legend: {
                position: 'top'
            }
        };

        var chart = new ApexCharts(document.querySelector("#performance-chart"), options);
        chart.render();
    })();
</script>
@endpush
