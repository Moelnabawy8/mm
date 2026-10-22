@extends('layouts.admin')

@section('page-title')
    {{ __('Edit API Client') }} - {{ $client->name }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('integration.index') }}">{{ __('Integration Hub') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('integration.clients') }}">{{ __('API Clients') }}</a></li>
    <li class="breadcrumb-item">{{ __('Edit') }}</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="ti ti-pencil me-2"></i>{{ __('Edit API Client') }}
                </h5>
            </div>
            <div class="card-body">
                <form action="{{ route('integration.clients.update', $client->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2">
                                <i class="ti ti-info-circle me-1"></i>{{ __('Basic Information') }}
                            </h6>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Client Name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                       value="{{ old('name', $client->name) }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Rate Limit') }} <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" name="rate_limit" class="form-control @error('rate_limit') is-invalid @enderror" 
                                           value="{{ old('rate_limit', $client->rate_limit) }}" min="1" max="1000" required>
                                    <span class="input-group-text">{{ __('requests/min') }}</span>
                                </div>
                                @error('rate_limit')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Description') }}</label>
                                <textarea name="description" class="form-control @error('description') is-invalid @enderror" 
                                          rows="2">{{ old('description', $client->description) }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input" name="is_active" id="is_active" value="1"
                                           {{ old('is_active', $client->is_active) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">{{ __('Active') }}</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2">
                                <i class="ti ti-shield-check me-1"></i>{{ __('Permissions (Scopes)') }}
                            </h6>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="selectAll" onchange="toggleAllAbilities(this)">
                                <label class="form-check-label fw-bold" for="selectAll">{{ __('Select All / Full Access') }}</label>
                            </div>
                            <hr>
                        </div>
                        
                        @php
                            $categories = [];
                            foreach($availableAbilities as $ability => $description) {
                                $parts = explode(':', $ability);
                                $cat = $parts[0] ?? 'other';
                                if(!isset($categories[$cat])) $categories[$cat] = [];
                                $categories[$cat][$ability] = $description;
                            }
                            $currentAbilities = old('abilities', $client->abilities ?? []);
                        @endphp
                        
                        @foreach($categories as $category => $abilities)
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card h-100 border">
                                <div class="card-header py-2 bg-light">
                                    <strong class="text-capitalize">{{ $category }}</strong>
                                </div>
                                <div class="card-body py-2">
                                    @foreach($abilities as $ability => $description)
                                    <div class="form-check mb-1">
                                        <input type="checkbox" class="form-check-input ability-checkbox" 
                                               name="abilities[]" value="{{ $ability }}"
                                               id="ability_{{ Str::slug($ability, '_') }}"
                                               {{ in_array($ability, $currentAbilities) ? 'checked' : '' }}>
                                        <label class="form-check-label small" for="ability_{{ Str::slug($ability, '_') }}">
                                            {{ $description }}
                                        </label>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        @endforeach
                        
                        @error('abilities')
                            <div class="col-12"><div class="text-danger small">{{ $message }}</div></div>
                        @enderror
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2">
                                <i class="ti ti-lock me-1"></i>{{ __('Security Settings') }}
                            </h6>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">{{ __('IP Whitelist') }}</label>
                                <input type="text" name="allowed_ips" class="form-control @error('allowed_ips') is-invalid @enderror" 
                                       value="{{ old('allowed_ips', $client->allowed_ips ? implode(', ', $client->allowed_ips) : '') }}"
                                       placeholder="{{ __('e.g., 192.168.1.1, 10.0.0.1') }}">
                                <div class="form-text">{{ __('Comma-separated IPs. Leave empty to allow all.') }}</div>
                                @error('allowed_ips')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Expiration Date') }}</label>
                                <input type="date" name="expires_at" class="form-control @error('expires_at') is-invalid @enderror" 
                                       value="{{ old('expires_at', $client->expires_at?->format('Y-m-d')) }}">
                                <div class="form-text">{{ __('Leave empty for no expiration.') }}</div>
                                @error('expires_at')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex justify-content-between">
                                <a href="{{ route('integration.clients.show', $client->id) }}" class="btn btn-outline-secondary">
                                    <i class="ti ti-arrow-left me-1"></i>{{ __('Cancel') }}
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="ti ti-check me-1"></i>{{ __('Save Changes') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script-page')
<script>
function toggleAllAbilities(checkbox) {
    document.querySelectorAll('.ability-checkbox').forEach(function(cb) {
        cb.checked = checkbox.checked;
    });
}

document.querySelectorAll('.ability-checkbox').forEach(function(cb) {
    cb.addEventListener('change', function() {
        const allChecked = document.querySelectorAll('.ability-checkbox:checked').length === 
                          document.querySelectorAll('.ability-checkbox').length;
        document.getElementById('selectAll').checked = allChecked;
    });
});

// Check "Select All" on page load if all are selected
document.addEventListener('DOMContentLoaded', function() {
    const allChecked = document.querySelectorAll('.ability-checkbox:checked').length === 
                      document.querySelectorAll('.ability-checkbox').length;
    document.getElementById('selectAll').checked = allChecked;
});
</script>
@endpush
