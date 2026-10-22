@extends('layouts.admin')

@section('page-title')
    {{ __('API Documentation') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('integration.index') }}">{{ __('Integration Hub') }}</a></li>
    <li class="breadcrumb-item">{{ __('Documentation') }}</li>
@endsection

@push('css-page')
<style>
    .code-block {
        background: #1e1e1e;
        color: #d4d4d4;
        padding: 15px;
        border-radius: 8px;
        font-family: 'Fira Code', monospace;
        font-size: 13px;
        overflow-x: auto;
    }
    .code-block .string { color: #ce9178; }
    .code-block .keyword { color: #569cd6; }
    .code-block .comment { color: #6a9955; }
    .method-badge {
        font-family: monospace;
        font-weight: bold;
        min-width: 70px;
        text-align: center;
    }
    .method-GET { background-color: #61affe !important; }
    .method-POST { background-color: #49cc90 !important; }
    .method-PUT { background-color: #fca130 !important; }
    .method-DELETE { background-color: #f93e3e !important; }
    .endpoint-path {
        font-family: monospace;
        background: #f8f9fa;
        padding: 5px 10px;
        border-radius: 4px;
    }
    .copy-btn {
        cursor: pointer;
        opacity: 0.7;
    }
    .copy-btn:hover {
        opacity: 1;
    }
</style>
@endpush

@section('content')
<div class="row">
    {{-- Getting Started --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="ti ti-rocket me-2"></i>{{ __('Getting Started with n8n Integration') }}</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>{{ __('Base URL') }}</h6>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" value="{{ $baseUrl }}" id="baseUrl" readonly>
                            <button class="btn btn-outline-secondary" onclick="copyToClipboard('baseUrl')">
                                <i class="ti ti-copy"></i>
                            </button>
                        </div>
                        
                        <h6>{{ __('Authentication') }}</h6>
                        <p>{{ __('All API requests must include the Authorization header:') }}</p>
                        <div class="code-block mb-3">
                            <span class="keyword">Authorization</span>: Bearer <span class="string">your_api_token</span>
                        </div>
                        
                        <h6>{{ __('Alternative Header') }}</h6>
                        <div class="code-block">
                            <span class="keyword">X-API-Key</span>: <span class="string">your_api_token</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>{{ __('n8n HTTP Request Node Setup') }}</h6>
                        <div class="code-block">
<span class="comment">// In n8n HTTP Request Node:</span>
{
  "<span class="keyword">method</span>": "<span class="string">GET</span>",
  "<span class="keyword">url</span>": "<span class="string">{{ $baseUrl }}/customers</span>",
  "<span class="keyword">authentication</span>": "<span class="string">genericCredentialType</span>",
  "<span class="keyword">genericAuthType</span>": "<span class="string">httpHeaderAuth</span>",
  "<span class="keyword">options</span>": {
    "<span class="keyword">headers</span>": {
      "<span class="keyword">Authorization</span>": "<span class="string">Bearer n8n_your_token_here</span>"
    }
  }
}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    {{-- Available Endpoints --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="ti ti-api me-2"></i>{{ __('Available API Endpoints') }}</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 100px;">{{ __('Method') }}</th>
                                <th>{{ __('Endpoint') }}</th>
                                <th style="width: 150px;">{{ __('Required Scope') }}</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Health Check --}}
                            <tr>
                                <td><span class="badge method-badge method-GET">GET</span></td>
                                <td><code class="endpoint-path">/api/n8n/health</code></td>
                                <td><span class="badge bg-secondary">none</span></td>
                                <td>
                                    <button class="btn btn-sm btn-link copy-btn" onclick="copyEndpoint('/api/n8n/health')">
                                        <i class="ti ti-copy"></i>
                                    </button>
                                </td>
                            </tr>
                            
                            {{-- Users --}}
                            <tr class="table-light"><td colspan="4"><strong>{{ __('Users') }}</strong></td></tr>
                            <tr>
                                <td><span class="badge method-badge method-GET">GET</span></td>
                                <td><code class="endpoint-path">/api/n8n/users</code></td>
                                <td><span class="badge bg-info">users:read</span></td>
                                <td><button class="btn btn-sm btn-link copy-btn" onclick="copyEndpoint('/api/n8n/users')"><i class="ti ti-copy"></i></button></td>
                            </tr>
                            <tr>
                                <td><span class="badge method-badge method-GET">GET</span></td>
                                <td><code class="endpoint-path">/api/n8n/users/{id}</code></td>
                                <td><span class="badge bg-info">users:read</span></td>
                                <td><button class="btn btn-sm btn-link copy-btn" onclick="copyEndpoint('/api/n8n/users/{id}')"><i class="ti ti-copy"></i></button></td>
                            </tr>
                            <tr>
                                <td><span class="badge method-badge method-POST">POST</span></td>
                                <td><code class="endpoint-path">/api/n8n/users</code></td>
                                <td><span class="badge bg-warning">users:create</span></td>
                                <td><button class="btn btn-sm btn-link copy-btn" onclick="copyEndpoint('/api/n8n/users')"><i class="ti ti-copy"></i></button></td>
                            </tr>
                            <tr>
                                <td><span class="badge method-badge method-PUT">PUT</span></td>
                                <td><code class="endpoint-path">/api/n8n/users/{id}</code></td>
                                <td><span class="badge bg-warning">users:update</span></td>
                                <td><button class="btn btn-sm btn-link copy-btn" onclick="copyEndpoint('/api/n8n/users/{id}')"><i class="ti ti-copy"></i></button></td>
                            </tr>
                            <tr>
                                <td><span class="badge method-badge method-DELETE">DELETE</span></td>
                                <td><code class="endpoint-path">/api/n8n/users/{id}</code></td>
                                <td><span class="badge bg-danger">users:delete</span></td>
                                <td><button class="btn btn-sm btn-link copy-btn" onclick="copyEndpoint('/api/n8n/users/{id}')"><i class="ti ti-copy"></i></button></td>
                            </tr>
                            
                            {{-- Customers --}}
                            <tr class="table-light"><td colspan="4"><strong>{{ __('Customers') }}</strong></td></tr>
                            <tr>
                                <td><span class="badge method-badge method-GET">GET</span></td>
                                <td><code class="endpoint-path">/api/n8n/customers</code></td>
                                <td><span class="badge bg-info">customers:read</span></td>
                                <td><button class="btn btn-sm btn-link copy-btn" onclick="copyEndpoint('/api/n8n/customers')"><i class="ti ti-copy"></i></button></td>
                            </tr>
                            <tr>
                                <td><span class="badge method-badge method-POST">POST</span></td>
                                <td><code class="endpoint-path">/api/n8n/customers</code></td>
                                <td><span class="badge bg-warning">customers:create</span></td>
                                <td><button class="btn btn-sm btn-link copy-btn" onclick="copyEndpoint('/api/n8n/customers')"><i class="ti ti-copy"></i></button></td>
                            </tr>
                            
                            {{-- Invoices --}}
                            <tr class="table-light"><td colspan="4"><strong>{{ __('Invoices') }}</strong></td></tr>
                            <tr>
                                <td><span class="badge method-badge method-GET">GET</span></td>
                                <td><code class="endpoint-path">/api/n8n/invoices</code></td>
                                <td><span class="badge bg-info">invoices:read</span></td>
                                <td><button class="btn btn-sm btn-link copy-btn" onclick="copyEndpoint('/api/n8n/invoices')"><i class="ti ti-copy"></i></button></td>
                            </tr>
                            <tr>
                                <td><span class="badge method-badge method-GET">GET</span></td>
                                <td><code class="endpoint-path">/api/n8n/invoices/{id}</code></td>
                                <td><span class="badge bg-info">invoices:read</span></td>
                                <td><button class="btn btn-sm btn-link copy-btn" onclick="copyEndpoint('/api/n8n/invoices/{id}')"><i class="ti ti-copy"></i></button></td>
                            </tr>
                            
                            {{-- Products --}}
                            <tr class="table-light"><td colspan="4"><strong>{{ __('Products') }}</strong></td></tr>
                            <tr>
                                <td><span class="badge method-badge method-GET">GET</span></td>
                                <td><code class="endpoint-path">/api/n8n/products</code></td>
                                <td><span class="badge bg-info">products:read</span></td>
                                <td><button class="btn btn-sm btn-link copy-btn" onclick="copyEndpoint('/api/n8n/products')"><i class="ti ti-copy"></i></button></td>
                            </tr>
                            <tr>
                                <td><span class="badge method-badge method-POST">POST</span></td>
                                <td><code class="endpoint-path">/api/n8n/products</code></td>
                                <td><span class="badge bg-warning">products:create</span></td>
                                <td><button class="btn btn-sm btn-link copy-btn" onclick="copyEndpoint('/api/n8n/products')"><i class="ti ti-copy"></i></button></td>
                            </tr>
                            
                            {{-- Leads --}}
                            <tr class="table-light"><td colspan="4"><strong>{{ __('Leads (CRM)') }}</strong></td></tr>
                            <tr>
                                <td><span class="badge method-badge method-GET">GET</span></td>
                                <td><code class="endpoint-path">/api/n8n/leads</code></td>
                                <td><span class="badge bg-info">leads:read</span></td>
                                <td><button class="btn btn-sm btn-link copy-btn" onclick="copyEndpoint('/api/n8n/leads')"><i class="ti ti-copy"></i></button></td>
                            </tr>
                            <tr>
                                <td><span class="badge method-badge method-POST">POST</span></td>
                                <td><code class="endpoint-path">/api/n8n/leads</code></td>
                                <td><span class="badge bg-warning">leads:create</span></td>
                                <td><button class="btn btn-sm btn-link copy-btn" onclick="copyEndpoint('/api/n8n/leads')"><i class="ti ti-copy"></i></button></td>
                            </tr>
                            
                            {{-- Webhook --}}
                            <tr class="table-light"><td colspan="4"><strong>{{ __('Webhook') }}</strong></td></tr>
                            <tr>
                                <td><span class="badge method-badge method-POST">POST</span></td>
                                <td><code class="endpoint-path">/api/n8n/webhook</code></td>
                                <td><span class="badge bg-danger">*</span></td>
                                <td><button class="btn btn-sm btn-link copy-btn" onclick="copyEndpoint('/api/n8n/webhook')"><i class="ti ti-copy"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Available Scopes --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="ti ti-shield-check me-2"></i>{{ __('Available Scopes') }}</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush" style="max-height: 600px; overflow-y: auto;">
                    @foreach($availableAbilities as $scope => $description)
                    <div class="list-group-item">
                        <code class="text-primary">{{ $scope }}</code>
                        <br><small class="text-muted">{{ $description }}</small>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        
        {{-- Response Codes --}}
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="ti ti-code me-2"></i>{{ __('Response Codes') }}</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <div class="list-group-item d-flex justify-content-between">
                        <span class="badge bg-success">200</span>
                        <span>{{ __('Success') }}</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <span class="badge bg-success">201</span>
                        <span>{{ __('Created') }}</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <span class="badge bg-warning">400</span>
                        <span>{{ __('Bad Request') }}</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <span class="badge bg-warning">401</span>
                        <span>{{ __('Unauthorized') }}</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <span class="badge bg-warning">403</span>
                        <span>{{ __('Forbidden') }}</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <span class="badge bg-warning">404</span>
                        <span>{{ __('Not Found') }}</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <span class="badge bg-warning">422</span>
                        <span>{{ __('Validation Error') }}</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <span class="badge bg-warning">429</span>
                        <span>{{ __('Rate Limited') }}</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <span class="badge bg-danger">500</span>
                        <span>{{ __('Server Error') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script-page')
<script>
function copyToClipboard(elementId) {
    const el = document.getElementById(elementId);
    el.select();
    document.execCommand('copy');
    alert('Copied to clipboard!');
}

function copyEndpoint(endpoint) {
    const baseUrl = document.getElementById('baseUrl').value.replace('/api/n8n', '');
    navigator.clipboard.writeText(baseUrl + endpoint).then(function() {
        alert('Endpoint copied: ' + baseUrl + endpoint);
    });
}
</script>
@endpush
