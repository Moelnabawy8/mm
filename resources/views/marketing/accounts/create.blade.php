@extends('layouts.admin')

@section('page-title')
    {{ __('Connect Social Media Account') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('marketing.index') }}">{{ __('Marketing') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('marketing.accounts') }}">{{ __('Accounts') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Connect') }}</li>
@endsection

@section('content')
    <div class="row justify-content-center">
        {{-- Facebook SDK Quick Connect --}}
        <div class="col-xl-10">
            <div class="card mb-4" style="border: 2px solid #1877F2; border-radius: 12px;">
                <div class="card-body text-center py-4">
                    <div class="mb-3">
                        <i class="ti ti-brand-facebook" style="font-size: 4rem; color: #1877F2;"></i>
                    </div>
                    <h4 class="mb-2">{{ __('Quick Connect with Facebook') }}</h4>
                    <p class="text-muted mb-3">{{ __('Connect your Facebook pages and Instagram business accounts automatically via Facebook Login') }}</p>
                    
                    @if(!empty($metaAppId))
                        <a href="{{ route('marketing.facebook.connect') }}" class="btn btn-lg" style="background-color: #1877F2; color: white; border-radius: 8px; padding: 12px 40px;">
                            <i class="ti ti-brand-facebook me-2"></i>{{ __('Connect with Facebook') }}
                        </a>
                        <p class="text-muted mt-2"><small>{{ __('This will discover all your Facebook Pages and linked Instagram accounts') }}</small></p>
                    @else
                        <div class="alert alert-warning d-inline-block">
                            <i class="ti ti-alert-triangle me-1"></i>
                            {{ __('To use Facebook Quick Connect, configure META_APP_ID and META_APP_SECRET in your .env file') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Divider --}}
        <div class="col-xl-10">
            <div class="d-flex align-items-center mb-4">
                <hr class="flex-grow-1">
                <span class="px-3 text-muted fw-bold">{{ __('OR Add Manually') }}</span>
                <hr class="flex-grow-1">
            </div>
        </div>

        {{-- Manual Add Single Account --}}
        <div class="col-xl-10">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ __('Add Account Manually') }}</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="toggleMultiAdd">
                        <i class="ti ti-plus me-1"></i>{{ __('Add Multiple Accounts') }}
                    </button>
                </div>
                <div class="card-body">
                    {{-- Single Account Form --}}
                    <div id="singleAccountForm">
                        <form action="{{ route('marketing.accounts.store') }}" method="POST">
                            @csrf
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('Platform') }} <span class="text-danger">*</span></label>
                                    <select name="platform" class="form-control" required id="platformSelect">
                                        <option value="">{{ __('Select Platform') }}</option>
                                        <option value="facebook" {{ old('platform') == 'facebook' ? 'selected' : '' }}>
                                            <i class="ti ti-brand-facebook"></i> {{ __('Facebook') }}
                                        </option>
                                        <option value="instagram" {{ old('platform') == 'instagram' ? 'selected' : '' }}>
                                            {{ __('Instagram') }}
                                        </option>
                                        <option value="twitter" {{ old('platform') == 'twitter' ? 'selected' : '' }}>
                                            {{ __('Twitter / X') }}
                                        </option>
                                        <option value="linkedin" {{ old('platform') == 'linkedin' ? 'selected' : '' }}>
                                            {{ __('LinkedIn') }}
                                        </option>
                                        <option value="youtube" {{ old('platform') == 'youtube' ? 'selected' : '' }}>
                                            {{ __('YouTube') }}
                                        </option>
                                        <option value="tiktok" {{ old('platform') == 'tiktok' ? 'selected' : '' }}>
                                            {{ __('TikTok') }}
                                        </option>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('Account Name') }} <span class="text-danger">*</span></label>
                                    <input type="text" name="account_name" class="form-control" placeholder="{{ __('My Business Page') }}" value="{{ old('account_name') }}" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('Account / Page ID') }} <small class="text-muted">({{ __('Optional') }})</small></label>
                                    <input type="text" name="page_id" class="form-control" placeholder="123456789" value="{{ old('page_id') }}">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('Page Name') }} <small class="text-muted">({{ __('Optional') }})</small></label>
                                    <input type="text" name="page_name" class="form-control" placeholder="{{ __('My Business Page Name') }}" value="{{ old('page_name') }}">
                                </div>

                                <div class="col-md-12 mb-3">
                                    <label class="form-label">{{ __('Access Token') }} <small class="text-muted">({{ __('Optional') }})</small></label>
                                    <textarea name="access_token" class="form-control" rows="3" placeholder="{{ __('Paste your access token here') }}">{{ old('access_token') }}</textarea>
                                    <small class="text-muted">{{ __('Get your access token from the platform\'s developer console') }}</small>
                                </div>
                            </div>

                            <div class="text-end">
                                <a href="{{ route('marketing.accounts') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                                <button type="submit" class="btn btn-primary">{{ __('Connect Account') }}</button>
                            </div>
                        </form>
                    </div>

                    {{-- Multiple Accounts Form --}}
                    <div id="multiAccountForm" style="display: none;">
                        <form action="{{ route('marketing.accounts.store') }}" method="POST">
                            @csrf
                            
                            <div id="accountsList">
                                {{-- Account Row Template --}}
                                <div class="account-row border rounded p-3 mb-3" data-index="0">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0"><span class="badge bg-primary">1</span> {{ __('Account') }}</h6>
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-account" style="display:none;">
                                            <i class="ti ti-x"></i>
                                        </button>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-3 mb-2">
                                            <label class="form-label">{{ __('Platform') }} <span class="text-danger">*</span></label>
                                            <select name="accounts[0][platform]" class="form-control" required>
                                                <option value="">{{ __('Select') }}</option>
                                                <option value="facebook">Facebook</option>
                                                <option value="instagram">Instagram</option>
                                                <option value="twitter">Twitter / X</option>
                                                <option value="linkedin">LinkedIn</option>
                                                <option value="youtube">YouTube</option>
                                                <option value="tiktok">TikTok</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <label class="form-label">{{ __('Account Name') }} <span class="text-danger">*</span></label>
                                            <input type="text" name="accounts[0][account_name]" class="form-control" required>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <label class="form-label">{{ __('Page ID') }}</label>
                                            <input type="text" name="accounts[0][page_id]" class="form-control">
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <label class="form-label">{{ __('Page Name') }}</label>
                                            <input type="text" name="accounts[0][page_name]" class="form-control">
                                        </div>
                                        <div class="col-md-12 mb-2">
                                            <label class="form-label">{{ __('Access Token') }}</label>
                                            <textarea name="accounts[0][access_token]" class="form-control" rows="2" placeholder="{{ __('Paste access token') }}"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <button type="button" class="btn btn-outline-success" id="addMoreAccounts">
                                    <i class="ti ti-plus me-1"></i>{{ __('Add Another Account') }}
                                </button>
                            </div>

                            <div class="text-end">
                                <a href="{{ route('marketing.accounts') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="ti ti-check me-1"></i>{{ __('Connect All Accounts') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('toggleMultiAdd');
    const singleForm = document.getElementById('singleAccountForm');
    const multiForm = document.getElementById('multiAccountForm');
    const addMoreBtn = document.getElementById('addMoreAccounts');
    const accountsList = document.getElementById('accountsList');
    let accountIndex = 1;
    let isMultiMode = false;

    // Toggle between single and multi mode
    toggleBtn.addEventListener('click', function() {
        isMultiMode = !isMultiMode;
        if (isMultiMode) {
            singleForm.style.display = 'none';
            multiForm.style.display = 'block';
            toggleBtn.innerHTML = '<i class="ti ti-arrow-back me-1"></i>{{ __("Single Account") }}';
        } else {
            singleForm.style.display = 'block';
            multiForm.style.display = 'none';
            toggleBtn.innerHTML = '<i class="ti ti-plus me-1"></i>{{ __("Add Multiple Accounts") }}';
        }
    });

    // Add more account rows
    addMoreBtn.addEventListener('click', function() {
        const newRow = document.createElement('div');
        newRow.className = 'account-row border rounded p-3 mb-3';
        newRow.dataset.index = accountIndex;
        newRow.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0"><span class="badge bg-primary">${accountIndex + 1}</span> {{ __('Account') }}</h6>
                <button type="button" class="btn btn-sm btn-outline-danger remove-account">
                    <i class="ti ti-x"></i>
                </button>
            </div>
            <div class="row">
                <div class="col-md-3 mb-2">
                    <label class="form-label">{{ __('Platform') }} <span class="text-danger">*</span></label>
                    <select name="accounts[${accountIndex}][platform]" class="form-control" required>
                        <option value="">{{ __('Select') }}</option>
                        <option value="facebook">Facebook</option>
                        <option value="instagram">Instagram</option>
                        <option value="twitter">Twitter / X</option>
                        <option value="linkedin">LinkedIn</option>
                        <option value="youtube">YouTube</option>
                        <option value="tiktok">TikTok</option>
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">{{ __('Account Name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="accounts[${accountIndex}][account_name]" class="form-control" required>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">{{ __('Page ID') }}</label>
                    <input type="text" name="accounts[${accountIndex}][page_id]" class="form-control">
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">{{ __('Page Name') }}</label>
                    <input type="text" name="accounts[${accountIndex}][page_name]" class="form-control">
                </div>
                <div class="col-md-12 mb-2">
                    <label class="form-label">{{ __('Access Token') }}</label>
                    <textarea name="accounts[${accountIndex}][access_token]" class="form-control" rows="2" placeholder="{{ __('Paste access token') }}"></textarea>
                </div>
            </div>
        `;
        accountsList.appendChild(newRow);
        accountIndex++;

        // Show remove buttons on all rows if more than 1
        updateRemoveButtons();
    });

    // Remove account row
    document.addEventListener('click', function(e) {
        if (e.target.closest('.remove-account')) {
            e.target.closest('.account-row').remove();
            updateRemoveButtons();
            updateBadges();
        }
    });

    function updateRemoveButtons() {
        const rows = accountsList.querySelectorAll('.account-row');
        rows.forEach(function(row) {
            const removeBtn = row.querySelector('.remove-account');
            if (removeBtn) {
                removeBtn.style.display = rows.length > 1 ? 'block' : 'none';
            }
        });
    }

    function updateBadges() {
        const rows = accountsList.querySelectorAll('.account-row');
        rows.forEach(function(row, index) {
            const badge = row.querySelector('.badge');
            if (badge) {
                badge.textContent = index + 1;
            }
        });
    }
});
</script>
@endpush
