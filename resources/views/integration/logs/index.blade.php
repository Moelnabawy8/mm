@extends('layouts.admin')

@section('page-title')
    {{ __('API Logs - Live Monitor') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('integration.index') }}">{{ __('Integration Hub') }}</a></li>
    <li class="breadcrumb-item">{{ __('API Logs') }}</li>
@endsection

@push('css-page')
<style>
    .log-row-success { background-color: rgba(25, 135, 84, 0.1) !important; }
    .log-row-warning { background-color: rgba(255, 193, 7, 0.1) !important; }
    .log-row-danger { background-color: rgba(220, 53, 69, 0.1) !important; }
    .payload-preview {
        max-height: 400px;
        overflow-y: auto;
        background: #1e1e1e;
        color: #d4d4d4;
        padding: 15px;
        border-radius: 8px;
        font-family: monospace;
        font-size: 12px;
    }
    .filter-card {
        border-left: 4px solid #0d6efd;
    }
</style>
@endpush

@section('content')
<div class="row">
    {{-- Filters --}}
    <div class="col-12">
        <div class="card filter-card">
            <div class="card-body">
                <form method="GET" action="{{ route('integration.logs') }}">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label">{{ __('API Client') }}</label>
                            <select name="client_id" class="form-select form-select-sm">
                                <option value="">{{ __('All Clients') }}</option>
                                @foreach($clients as $id => $name)
                                    <option value="{{ $id }}" {{ request('client_id') == $id ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">{{ __('Status') }}</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="">{{ __('All') }}</option>
                                <option value="success" {{ request('status') == 'success' ? 'selected' : '' }}>{{ __('Success (2xx)') }}</option>
                                <option value="client_error" {{ request('status') == 'client_error' ? 'selected' : '' }}>{{ __('Client Error (4xx)') }}</option>
                                <option value="server_error" {{ request('status') == 'server_error' ? 'selected' : '' }}>{{ __('Server Error (5xx)') }}</option>
                                <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>{{ __('All Errors') }}</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">{{ __('Method') }}</label>
                            <select name="method" class="form-select form-select-sm">
                                <option value="">{{ __('All') }}</option>
                                <option value="GET" {{ request('method') == 'GET' ? 'selected' : '' }}>GET</option>
                                <option value="POST" {{ request('method') == 'POST' ? 'selected' : '' }}>POST</option>
                                <option value="PUT" {{ request('method') == 'PUT' ? 'selected' : '' }}>PUT</option>
                                <option value="DELETE" {{ request('method') == 'DELETE' ? 'selected' : '' }}>DELETE</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">{{ __('From Date') }}</label>
                            <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">{{ __('To Date') }}</label>
                            <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                <i class="ti ti-filter me-1"></i>{{ __('Filter') }}
                            </button>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-4">
                            <input type="text" name="search" class="form-control form-control-sm" 
                                   placeholder="{{ __('Search endpoint...') }}" value="{{ request('search') }}">
                        </div>
                        <div class="col-md-2">
                            <a href="{{ route('integration.logs') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="ti ti-x me-1"></i>{{ __('Clear') }}
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="ti ti-list me-1"></i>{{ __('API Request Logs') }}
                </h5>
                <span class="badge bg-info">{{ $logs->total() }} {{ __('records') }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 140px;">{{ __('Time') }}</th>
                                <th style="width: 100px;">{{ __('Client') }}</th>
                                <th style="width: 80px;">{{ __('Method') }}</th>
                                <th>{{ __('Endpoint') }}</th>
                                <th style="width: 80px;">{{ __('Status') }}</th>
                                <th style="width: 90px;">{{ __('Time (ms)') }}</th>
                                <th style="width: 120px;">{{ __('IP') }}</th>
                                <th style="width: 100px;" class="text-end">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                            <tr class="log-row-{{ $log->status_category }}">
                                <td class="small">
                                    {{ $log->created_at->format('M d, H:i:s') }}
                                </td>
                                <td class="small">
                                    @if($log->client)
                                        <a href="{{ route('integration.clients.show', $log->api_client_id) }}">
                                            {{ Str::limit($log->client->name, 15) }}
                                        </a>
                                    @else
                                        <span class="text-muted">{{ __('Unknown') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-secondary">{{ $log->method }}</span>
                                </td>
                                <td>
                                    <code class="small" title="{{ $log->endpoint }}">
                                        {{ Str::limit($log->endpoint, 50) }}
                                    </code>
                                    @if($log->error_message)
                                        <br><small class="text-danger">{{ Str::limit($log->error_message, 50) }}</small>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $log->status_badge_class }}">
                                        {{ $log->response_code }}
                                    </span>
                                </td>
                                <td class="small text-end">
                                    {{ $log->response_time ? number_format($log->response_time, 1) : '-' }}
                                </td>
                                <td class="small">
                                    <code>{{ $log->ip_address }}</code>
                                </td>
                                <td class="text-end">
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-info view-payload-btn"
                                            data-log-id="{{ $log->id }}"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#payloadModal">
                                        <i class="ti ti-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="ti ti-database-off fs-1 d-block mb-2"></i>
                                        {{ __('No logs found matching your criteria.') }}
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                {{ $logs->withQueryString()->links() }}
            </div>
        </div>
    </div>
</div>

{{-- Payload Modal --}}
<div class="modal fade" id="payloadModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Request Details') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="payloadLoading" class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-2">{{ __('Loading...') }}</p>
                </div>
                <div id="payloadContent" style="display: none;">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>{{ __('Method') }}:</strong> <span id="modal-method" class="badge bg-secondary"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>{{ __('Status') }}:</strong> <span id="modal-status" class="badge"></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <strong>{{ __('Endpoint') }}:</strong>
                        <code id="modal-endpoint" class="d-block mt-1"></code>
                    </div>
                    <div class="mb-3">
                        <strong>{{ __('Full URL') }}:</strong>
                        <code id="modal-url" class="d-block mt-1 small"></code>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>{{ __('IP Address') }}:</strong> <code id="modal-ip"></code>
                        </div>
                        <div class="col-md-6">
                            <strong>{{ __('Response Time') }}:</strong> <span id="modal-time"></span>
                        </div>
                    </div>
                    
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-payload">
                                {{ __('Request Payload') }}
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-response">
                                {{ __('Response') }}
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-headers">
                                {{ __('Headers') }}
                            </button>
                        </li>
                    </ul>
                    <div class="tab-content mt-3">
                        <div class="tab-pane fade show active" id="tab-payload">
                            <pre class="payload-preview" id="modal-payload"></pre>
                        </div>
                        <div class="tab-pane fade" id="tab-response">
                            <pre class="payload-preview" id="modal-response"></pre>
                        </div>
                        <div class="tab-pane fade" id="tab-headers">
                            <pre class="payload-preview" id="modal-headers"></pre>
                        </div>
                    </div>
                    
                    <div id="modal-error" class="mt-3" style="display: none;">
                        <div class="alert alert-danger">
                            <strong>{{ __('Error Message') }}:</strong>
                            <p id="modal-error-message" class="mb-0"></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script-page')
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.view-payload-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const logId = this.dataset.logId;
            const loading = document.getElementById('payloadLoading');
            const content = document.getElementById('payloadContent');
            
            loading.style.display = 'block';
            content.style.display = 'none';
            
            fetch('{{ url("integration/logs") }}/' + logId)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('modal-method').textContent = data.method;
                    document.getElementById('modal-status').textContent = data.response_code;
                    document.getElementById('modal-status').className = 'badge ' + 
                        (data.response_code < 400 ? 'bg-success' : 'bg-danger');
                    document.getElementById('modal-endpoint').textContent = data.endpoint;
                    document.getElementById('modal-url').textContent = data.full_url || '-';
                    document.getElementById('modal-ip').textContent = data.ip_address;
                    document.getElementById('modal-time').textContent = data.response_time ? data.response_time + 'ms' : '-';
                    document.getElementById('modal-payload').textContent = JSON.stringify(data.payload, null, 2) || '{}';
                    document.getElementById('modal-response').textContent = JSON.stringify(data.response_body, null, 2) || '{}';
                    document.getElementById('modal-headers').textContent = JSON.stringify(data.headers, null, 2) || '{}';
                    
                    if (data.error_message) {
                        document.getElementById('modal-error').style.display = 'block';
                        document.getElementById('modal-error-message').textContent = data.error_message;
                    } else {
                        document.getElementById('modal-error').style.display = 'none';
                    }
                    
                    loading.style.display = 'none';
                    content.style.display = 'block';
                })
                .catch(error => {
                    loading.innerHTML = '<div class="text-danger">Error loading data</div>';
                });
        });
    });
});
</script>
@endpush
