@extends('layouts.admin')

@section('page-title')
    {{ __('Integration Hub') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Integration Hub') }}</li>
@endsection

@push('css-page')
<style>
    .stat-card {
        transition: transform 0.2s;
    }
    .stat-card:hover {
        transform: translateY(-2px);
    }
    .stat-icon {
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
    }
    .log-row-success { background-color: rgba(25, 135, 84, 0.1) !important; }
    .log-row-warning { background-color: rgba(255, 193, 7, 0.1) !important; }
    .log-row-danger { background-color: rgba(220, 53, 69, 0.1) !important; }
</style>
@endpush

@section('content')
<div class="row">
    {{-- Statistics Cards --}}
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-primary text-white me-3">
                        <i class="ti ti-plug-connected fs-4"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 text-muted">{{ __('Total API Clients') }}</h6>
                        <h3 class="mb-0">{{ $stats['total_clients'] }}</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-success text-white me-3">
                        <i class="ti ti-check fs-4"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 text-muted">{{ __('Active Clients') }}</h6>
                        <h3 class="mb-0">{{ $stats['active_clients'] }}</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-info text-white me-3">
                        <i class="ti ti-api fs-4"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 text-muted">{{ __('Today\'s Requests') }}</h6>
                        <h3 class="mb-0" id="todayRequests">{{ $stats['today_requests'] }}</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-danger text-white me-3">
                        <i class="ti ti-alert-triangle fs-4"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 text-muted">{{ __('Failed Today') }}</h6>
                        <h3 class="mb-0" id="todayFailed">{{ $stats['today_failed'] }}</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    {{-- Quick Actions --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('Quick Actions') }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-auto">
                        <a href="{{ route('integration.clients.create') }}" class="btn btn-primary">
                            <i class="ti ti-plus me-1"></i>{{ __('Create n8n API Key') }}
                        </a>
                    </div>
                    <div class="col-auto">
                        <a href="{{ route('integration.clients') }}" class="btn btn-outline-primary">
                            <i class="ti ti-key me-1"></i>{{ __('Manage API Clients') }}
                        </a>
                    </div>
                    <div class="col-auto">
                        <a href="{{ route('integration.logs') }}" class="btn btn-outline-info">
                            <i class="ti ti-list me-1"></i>{{ __('View All Logs') }}
                        </a>
                    </div>
                    <div class="col-auto">
                        <a href="{{ route('integration.docs') }}" class="btn btn-outline-secondary">
                            <i class="ti ti-book me-1"></i>{{ __('API Documentation') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    {{-- Active Clients --}}
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('Active API Clients') }}</h5>
                <a href="{{ route('integration.clients') }}" class="btn btn-sm btn-outline-primary">
                    {{ __('View All') }}
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Today') }}</th>
                                <th>{{ __('Last Used') }}</th>
                                <th>{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($clients as $client)
                            <tr>
                                <td>
                                    <a href="{{ route('integration.clients.show', $client->id) }}">
                                        {{ $client->name }}
                                    </a>
                                </td>
                                <td>{{ $client->today_requests ?? 0 }}</td>
                                <td>
                                    @if($client->last_used_at)
                                        {{ $client->last_used_at->diffForHumans() }}
                                    @else
                                        <span class="text-muted">{{ __('Never') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($client->is_active)
                                        <span class="badge bg-success">{{ __('Active') }}</span>
                                    @else
                                        <span class="badge bg-danger">{{ __('Inactive') }}</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">
                                    {{ __('No API clients yet.') }}
                                    <a href="{{ route('integration.clients.create') }}">{{ __('Create one') }}</a>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Recent Logs --}}
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('Recent API Activity') }}</h5>
                <a href="{{ route('integration.logs') }}" class="btn btn-sm btn-outline-primary">
                    {{ __('View All') }}
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Endpoint') }}</th>
                                <th>{{ __('Method') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Time') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentLogs as $log)
                            <tr class="log-row-{{ $log->status_category }}">
                                <td>
                                    <code class="small">{{ Str::limit($log->endpoint, 30) }}</code>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">{{ $log->method }}</span>
                                </td>
                                <td>
                                    <span class="badge {{ $log->status_badge_class }}">
                                        {{ $log->response_code }}
                                    </span>
                                </td>
                                <td class="small text-muted">
                                    {{ $log->created_at->diffForHumans() }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">
                                    {{ __('No API requests yet.') }}
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

{{-- Hourly Chart --}}
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Today\'s API Requests (Hourly)') }}</h5>
            </div>
            <div class="card-body">
                <canvas id="hourlyChart" height="100"></canvas>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script-page')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Hourly chart
    const hourlyData = @json($hourlyStats);
    const hours = Array.from({length: 24}, (_, i) => i.toString().padStart(2, '0') + ':00');
    const values = hours.map((_, i) => hourlyData[i] || 0);
    
    const ctx = document.getElementById('hourlyChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: hours,
            datasets: [{
                label: '{{ __("Requests") }}',
                data: values,
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
    
    // Auto-refresh stats every 30 seconds
    setInterval(function() {
        fetch('{{ route("integration.stats.api") }}')
            .then(response => response.json())
            .then(data => {
                document.getElementById('todayRequests').textContent = data.today_requests;
                document.getElementById('todayFailed').textContent = data.today_failed;
            });
    }, 30000);
});
</script>
@endpush
