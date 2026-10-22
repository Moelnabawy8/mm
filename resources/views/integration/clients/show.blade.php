@extends('layouts.admin')

@section('page-title')
    {{ __('API Client Details') }} - {{ $client->name }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('integration.index') }}">{{ __('Integration Hub') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('integration.clients') }}">{{ __('API Clients') }}</a></li>
    <li class="breadcrumb-item">{{ $client->name }}</li>
@endsection

@section('action-btn')
    <a href="{{ route('integration.clients.edit', $client->id) }}" class="btn btn-info btn-sm">
        <i class="ti ti-pencil me-1"></i>{{ __('Edit') }}
    </a>
@endsection

@push('css-page')
<style>
    .token-display {
        background: #1e1e1e;
        color: #4ec9b0;
        padding: 15px;
        border-radius: 8px;
        font-family: monospace;
        word-break: break-all;
    }
    .log-row-success { background-color: rgba(25, 135, 84, 0.1) !important; }
    .log-row-warning { background-color: rgba(255, 193, 7, 0.1) !important; }
    .log-row-danger { background-color: rgba(220, 53, 69, 0.1) !important; }
</style>
@endpush

@section('content')
{{-- New Token Alert --}}
@if(session('new_api_token'))
<div class="row">
    <div class="col-12">
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <h5 class="alert-heading">
                <i class="ti ti-check me-1"></i>{{ __('API Key Generated Successfully!') }}
            </h5>
            <p class="mb-2">{{ __('Copy your API token now. For security, it won\'t be shown again.') }}</p>
            <div class="token-display mb-3">
                <span id="apiToken">{{ session('new_api_token') }}</span>
                <button type="button" class="btn btn-sm btn-light float-end" onclick="copyToken()">
                    <i class="ti ti-copy"></i> {{ __('Copy') }}
                </button>
            </div>
            <hr>
            <p class="mb-0 small">
                {{ __('Use this token in n8n with the header:') }} 
                <code>Authorization: Bearer {{ session('new_api_token') }}</code>
            </p>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
</div>
@endif

<div class="row">
    {{-- Client Info Card --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('Client Information') }}</h5>
                @if($client->is_active)
                    <span class="badge bg-success">{{ __('Active') }}</span>
                @else
                    <span class="badge bg-danger">{{ __('Revoked') }}</span>
                @endif
            </div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr>
                        <th class="ps-0">{{ __('Name') }}</th>
                        <td class="pe-0">{{ $client->name }}</td>
                    </tr>
                    <tr>
                        <th class="ps-0">{{ __('Token Prefix') }}</th>
                        <td class="pe-0"><code>{{ $client->token_prefix }}...</code></td>
                    </tr>
                    <tr>
                        <th class="ps-0">{{ __('Rate Limit') }}</th>
                        <td class="pe-0">{{ $client->rate_limit }} {{ __('req/min') }}</td>
                    </tr>
                    <tr>
                        <th class="ps-0">{{ __('Created') }}</th>
                        <td class="pe-0">{{ $client->created_at->format('M d, Y H:i') }}</td>
                    </tr>
                    <tr>
                        <th class="ps-0">{{ __('Expires') }}</th>
                        <td class="pe-0">
                            @if($client->expires_at)
                                {{ $client->expires_at->format('M d, Y') }}
                                @if($client->isExpired())
                                    <span class="badge bg-danger">{{ __('Expired') }}</span>
                                @endif
                            @else
                                <span class="text-muted">{{ __('Never') }}</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th class="ps-0">{{ __('Last Used') }}</th>
                        <td class="pe-0">
                            @if($client->last_used_at)
                                {{ $client->last_used_at->diffForHumans() }}
                            @else
                                <span class="text-muted">{{ __('Never') }}</span>
                            @endif
                        </td>
                    </tr>
                </table>
                
                @if($client->description)
                <hr>
                <p class="text-muted mb-0 small">{{ $client->description }}</p>
                @endif
            </div>
        </div>
        
        {{-- Statistics Card --}}
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Statistics') }}</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <h3 class="mb-0">{{ number_format($stats['total_requests']) }}</h3>
                        <small class="text-muted">{{ __('Total Requests') }}</small>
                    </div>
                    <div class="col-6 mb-3">
                        <h3 class="mb-0">{{ $stats['today_requests'] }}</h3>
                        <small class="text-muted">{{ __('Today') }}</small>
                    </div>
                    <div class="col-6">
                        <h3 class="mb-0 text-danger">{{ $stats['today_failed'] }}</h3>
                        <small class="text-muted">{{ __('Failed Today') }}</small>
                    </div>
                    <div class="col-6">
                        <h3 class="mb-0 text-success">{{ $stats['success_rate'] }}%</h3>
                        <small class="text-muted">{{ __('Success Rate') }}</small>
                    </div>
                </div>
            </div>
        </div>
        
        {{-- IP Whitelist Card --}}
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('IP Whitelist') }}</h5>
            </div>
            <div class="card-body">
                @if($client->allowed_ips && count($client->allowed_ips) > 0)
                    @foreach($client->allowed_ips as $ip)
                        <span class="badge bg-secondary me-1 mb-1">{{ $ip }}</span>
                    @endforeach
                @else
                    <span class="text-muted">{{ __('All IPs allowed') }}</span>
                @endif
            </div>
        </div>
    </div>
    
    {{-- Abilities & Logs --}}
    <div class="col-lg-8">
        {{-- Abilities Card --}}
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Permissions (Abilities)') }}</h5>
            </div>
            <div class="card-body">
                @if(in_array('*', $client->abilities ?? []))
                    <span class="badge bg-danger fs-6">
                        <i class="ti ti-shield-check me-1"></i>{{ __('Full Access (All Permissions)') }}
                    </span>
                @else
                    <div class="row">
                        @foreach($client->abilities ?? [] as $ability)
                        <div class="col-md-4 mb-2">
                            <span class="badge bg-primary">
                                {{ $availableAbilities[$ability] ?? $ability }}
                            </span>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        
        {{-- Recent Logs Card --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('Recent Activity') }}</h5>
                <a href="{{ route('integration.logs', ['client_id' => $client->id]) }}" class="btn btn-sm btn-outline-primary">
                    {{ __('View All Logs') }}
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Time') }}</th>
                                <th>{{ __('Method') }}</th>
                                <th>{{ __('Endpoint') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Response Time') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentLogs as $log)
                            <tr class="log-row-{{ $log->status_category }}">
                                <td class="small">{{ $log->created_at->format('H:i:s') }}</td>
                                <td><span class="badge bg-secondary">{{ $log->method }}</span></td>
                                <td><code class="small">{{ Str::limit($log->endpoint, 40) }}</code></td>
                                <td>
                                    <span class="badge {{ $log->status_badge_class }}">
                                        {{ $log->response_code }}
                                    </span>
                                </td>
                                <td class="small">{{ $log->response_time ? $log->response_time . 'ms' : '-' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    {{ __('No API requests yet.') }}
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        {{-- Actions Card --}}
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Actions') }}</h5>
            </div>
            <div class="card-body">
                <div class="d-flex gap-2 flex-wrap">
                    @if($client->is_active)
                        <form action="{{ route('integration.clients.revoke', $client->id) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-warning" onclick="return confirm('{{ __('Are you sure?') }}')">
                                <i class="ti ti-ban me-1"></i>{{ __('Revoke Token') }}
                            </button>
                        </form>
                    @else
                        <form action="{{ route('integration.clients.activate', $client->id) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-success">
                                <i class="ti ti-check me-1"></i>{{ __('Activate Token') }}
                            </button>
                        </form>
                    @endif
                    
                    <form action="{{ route('integration.clients.destroy', $client->id) }}" method="POST" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger" onclick="return confirm('{{ __('This will permanently delete this API client. Are you sure?') }}')">
                            <i class="ti ti-trash me-1"></i>{{ __('Delete Permanently') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script-page')
<script>
function copyToken() {
    const token = document.getElementById('apiToken').textContent;
    navigator.clipboard.writeText(token).then(function() {
        alert('{{ __("Token copied to clipboard!") }}');
    });
}
</script>
@endpush
