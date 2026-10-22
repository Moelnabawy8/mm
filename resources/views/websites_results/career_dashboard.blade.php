@extends('layouts.admin')

@section('page-title')
    {{ __('Career Path Dashboard') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Websites') }}</li>
    <li class="breadcrumb-item">{{ __('Career Path') }}</li>
@endsection

@section('content')
    {{-- Navigation Tabs --}}
    <div class="row mb-3">
        <div class="col-12">
            <ul class="nav nav-pills">
                <li class="nav-item">
                    <a class="nav-link active" href="{{ route('websites.career.dashboard') }}">
                        <i class="ti ti-dashboard me-1"></i>{{ __('Dashboard') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('websites.career.users') }}">
                        <i class="ti ti-users me-1"></i>{{ __('Users') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('websites.career.assessments') }}">
                        <i class="ti ti-clipboard-check me-1"></i>{{ __('Assessments') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('websites.career.cvs') }}">
                        <i class="ti ti-file-text me-1"></i>{{ __('CVs') }}
                    </a>
                </li>
            </ul>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="row">
        @php
            $cards = [
                ['label' => __('Total Users'),       'value' => number_format($stats['totalUsers'] ?? 0),       'icon' => 'users',           'color' => 'primary'],
                ['label' => __('Total Assessments'), 'value' => number_format($stats['totalAssessments'] ?? 0), 'icon' => 'clipboard-check', 'color' => 'success'],
                ['label' => __('Generated CVs'),     'value' => number_format($stats['totalCVs'] ?? 0),         'icon' => 'file-text',       'color' => 'info'],
                ['label' => __('Active Users'),      'value' => number_format($stats['activeUsers'] ?? 0),      'icon' => 'user-check',      'color' => 'warning'],
                ['label' => __('AI Tokens Used'),    'value' => number_format($stats['totalTokensUsed'] ?? 0),  'icon' => 'bolt',            'color' => 'danger'],
            ];
        @endphp
        @foreach ($cards as $c)
            <div class="col-xl-3 col-md-6 col-sm-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted mb-1">{{ $c['label'] }}</p>
                                <h3 class="mb-0">{{ $c['value'] }}</h3>
                            </div>
                            <span class="avtar avtar-l bg-light-{{ $c['color'] }}">
                                <i class="ti ti-{{ $c['icon'] }} fs-3"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Assessments by Track Chart --}}
    @if(!empty($stats['assessmentsByTrack']))
    <div class="row">
        <div class="col-xl-6 col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ti ti-chart-bar me-1"></i> {{ __('Assessments by Track') }}</h5>
                </div>
                <div class="card-body">
                    <canvas id="trackChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-6 col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ti ti-list me-1"></i> {{ __('Track Breakdown') }}</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Track') }}</th>
                                    <th class="text-end">{{ __('Count') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($stats['assessmentsByTrack'] as $track => $count)
                                    <tr>
                                        <td>{{ $track }}</td>
                                        <td class="text-end"><span class="badge bg-primary">{{ number_format($count) }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Recent Data Tables --}}
    <div class="row">
        {{-- Recent Users --}}
        <div class="col-xl-4 col-md-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="ti ti-users me-1"></i> {{ __('Recent Users') }}</h5>
                    <a href="{{ route('websites.career.users') }}" class="btn btn-sm btn-outline-primary">{{ __('View All') }}</a>
                </div>
                <div class="card-body p-0">
                    <ul class="list-unstyled mb-0">
                        @forelse (($recentUsers['users'] ?? []) as $user)
                            <li class="d-flex justify-content-between border-bottom px-3 py-2">
                                <div>
                                    <strong>{{ $user['display_name'] ?? __('N/A') }}</strong>
                                    <br><small class="text-muted">{{ $user['email'] ?? '' }}</small>
                                </div>
                                <small class="text-muted">{{ isset($user['created_at']) ? \Carbon\Carbon::parse($user['created_at'])->diffForHumans() : '' }}</small>
                            </li>
                        @empty
                            <li class="text-center text-muted p-4">{{ __('No data') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        {{-- Recent Assessments --}}
        <div class="col-xl-4 col-md-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="ti ti-clipboard-check me-1"></i> {{ __('Recent Assessments') }}</h5>
                    <a href="{{ route('websites.career.assessments') }}" class="btn btn-sm btn-outline-success">{{ __('View All') }}</a>
                </div>
                <div class="card-body p-0">
                    <ul class="list-unstyled mb-0">
                        @forelse (($recentAssessments['assessments'] ?? []) as $a)
                            <li class="d-flex justify-content-between border-bottom px-3 py-2">
                                <div>
                                    <strong>{{ $a['user_name'] ?? __('N/A') }}</strong>
                                    <br><small class="text-muted">{{ $a['track_id'] ?? '' }}</small>
                                </div>
                                <small class="text-muted">{{ isset($a['created_at']) ? \Carbon\Carbon::parse($a['created_at'])->diffForHumans() : '' }}</small>
                            </li>
                        @empty
                            <li class="text-center text-muted p-4">{{ __('No data') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        {{-- Recent CVs --}}
        <div class="col-xl-4 col-md-12">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="ti ti-file-text me-1"></i> {{ __('Recent CVs') }}</h5>
                    <a href="{{ route('websites.career.cvs') }}" class="btn btn-sm btn-outline-info">{{ __('View All') }}</a>
                </div>
                <div class="card-body p-0">
                    <ul class="list-unstyled mb-0">
                        @forelse (($recentCvs['cvs'] ?? []) as $cv)
                            <li class="d-flex justify-content-between border-bottom px-3 py-2">
                                <div>
                                    <strong>{{ $cv['user_name'] ?? __('N/A') }}</strong>
                                    <br><small class="text-muted">{{ $cv['job_title'] ?? '' }}</small>
                                </div>
                                <small class="text-muted">{{ isset($cv['created_at']) ? \Carbon\Carbon::parse($cv['created_at'])->diffForHumans() : '' }}</small>
                            </li>
                        @empty
                            <li class="text-center text-muted p-4">{{ __('No data') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script-page')
@if(!empty($stats['assessmentsByTrack']))
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
(function () {
    var trackData = @json($stats['assessmentsByTrack'] ?? []);
    var labels = Object.keys(trackData);
    var values = Object.values(trackData);
    var colors = ['#4e73df','#1cc88a','#36b9cc','#f6c23e','#e74a3b','#858796','#5a5c69','#2e59d9'];

    new Chart(document.getElementById('trackChart'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: '{{ __("Assessments") }}',
                data: values,
                backgroundColor: labels.map(function(_, i) { return colors[i % colors.length]; }),
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
})();
</script>
@endif
@endpush
