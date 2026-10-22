@extends('layouts.admin')

@section('page-title')
    {{ $campaign->campaign_name }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('marketing.index') }}">{{ __('Marketing') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('marketing.campaigns') }}">{{ __('Campaigns') }}</a></li>
    <li class="breadcrumb-item active">{{ $campaign->campaign_name }}</li>
@endsection

@section('action-btn')
    <form action="{{ route('marketing.campaigns.sync', $campaign->id) }}" method="POST" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-sm btn-info">
            <i class="ti ti-refresh"></i> {{ __('Sync Data') }}
        </button>
    </form>
    <a href="{{ route('marketing.campaigns.edit', $campaign->id) }}" class="btn btn-sm btn-primary">
        <i class="ti ti-edit"></i> {{ __('Edit') }}
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
                    <h6 class="text-muted mb-1">{{ __('Total Impressions') }}</h6>
                    <h3 class="mb-0">{{ number_format($campaign->total_impressions) }}</h3>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-1">{{ __('Total Clicks') }}</h6>
                    <h3 class="mb-0">{{ number_format($campaign->total_clicks) }}</h3>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-1">{{ __('Total Spent') }}</h6>
                    <h3 class="mb-0">${{ number_format($campaign->total_spent, 2) }}</h3>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-1">{{ __('Avg CTR') }}</h6>
                    <h3 class="mb-0">{{ number_format($campaign->average_ctr, 2) }}%</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <h5>{{ __('Performance Over Time') }}</h5>
                </div>
                <div class="card-body">
                    <div id="campaign-chart"></div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card">
                <div class="card-header">
                    <h5>{{ __('Campaign Details') }}</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="text-muted">{{ __('Account') }}</small>
                        <p class="mb-0">
                            <i class="{{ $campaign->account->platform_icon }}" style="color: {{ $campaign->account->platform_color }};"></i>
                            {{ $campaign->account->account_name }}
                        </p>
                    </div>

                    <div class="mb-3">
                        <small class="text-muted">{{ __('Objective') }}</small>
                        <p class="mb-0"><span class="badge bg-info">{{ ucfirst($campaign->objective) }}</span></p>
                    </div>

                    <div class="mb-3">
                        <small class="text-muted">{{ __('Status') }}</small>
                        <p class="mb-0">
                            @php
                                $statusColors = [
                                    'active' => 'success',
                                    'paused' => 'warning',
                                    'draft' => 'secondary',
                                    'completed' => 'info',
                                    'cancelled' => 'danger'
                                ];
                            @endphp
                            <span class="badge bg-{{ $statusColors[$campaign->status] ?? 'secondary' }}">
                                {{ ucfirst($campaign->status) }}
                            </span>
                        </p>
                    </div>

                    <div class="mb-3">
                        <small class="text-muted">{{ __('Budget') }}</small>
                        <p class="mb-0"><strong>${{ number_format($campaign->budget, 2) }}</strong> {{ $campaign->currency }}</p>
                    </div>

                    @if($campaign->start_date)
                        <div class="mb-3">
                            <small class="text-muted">{{ __('Start Date') }}</small>
                            <p class="mb-0">{{ $campaign->start_date->format('M d, Y') }}</p>
                        </div>
                    @endif

                    @if($campaign->end_date)
                        <div class="mb-3">
                            <small class="text-muted">{{ __('End Date') }}</small>
                            <p class="mb-0">{{ $campaign->end_date->format('M d, Y') }}</p>
                        </div>
                    @endif

                    @if($campaign->description)
                        <div class="mb-3">
                            <small class="text-muted">{{ __('Description') }}</small>
                            <p class="mb-0">{{ $campaign->description }}</p>
                        </div>
                    @endif

                    <div class="mb-3">
                        <small class="text-muted">{{ __('Created') }}</small>
                        <p class="mb-0">{{ $campaign->created_at->format('M d, Y') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>{{ __('Daily Results') }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Impressions') }}</th>
                                    <th>{{ __('Clicks') }}</th>
                                    <th>{{ __('Reach') }}</th>
                                    <th>{{ __('Conversions') }}</th>
                                    <th>{{ __('Spend') }}</th>
                                    <th>{{ __('CPC') }}</th>
                                    <th>{{ __('CTR') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($results as $result)
                                    <tr>
                                        <td>{{ $result->date->format('M d, Y') }}</td>
                                        <td>{{ number_format($result->impressions) }}</td>
                                        <td>{{ number_format($result->clicks) }}</td>
                                        <td>{{ number_format($result->reach) }}</td>
                                        <td>{{ number_format($result->conversions) }}</td>
                                        <td>${{ number_format($result->spend, 2) }}</td>
                                        <td>${{ number_format($result->cpc, 4) }}</td>
                                        <td>{{ number_format($result->ctr, 2) }}%</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <p class="text-muted">{{ __('No results data available yet') }}</p>
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
                data: {!! json_encode($chartData['impressions']) !!}
            }, {
                name: '{{ __("Clicks") }}',
                data: {!! json_encode($chartData['clicks']) !!}
            }, {
                name: '{{ __("Conversions") }}',
                data: {!! json_encode($chartData['conversions']) !!}
            }],
            chart: {
                height: 350,
                type: 'line',
                toolbar: {
                    show: false
                }
            },
            stroke: {
                curve: 'smooth',
                width: 2
            },
            xaxis: {
                categories: {!! json_encode($chartData['labels']) !!}
            },
            colors: ['#6fd943', '#ffa21d', '#ff3a6e']
        };

        var chart = new ApexCharts(document.querySelector("#campaign-chart"), options);
        chart.render();
    })();
</script>
@endpush
