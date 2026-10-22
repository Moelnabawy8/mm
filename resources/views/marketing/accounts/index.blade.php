@extends('layouts.admin')

@section('page-title')
    {{ __('Social Media Accounts') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('marketing.index') }}">{{ __('Marketing') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Accounts') }}</li>
@endsection

@section('action-btn')
    <a href="{{ route('marketing.accounts.create') }}" class="btn btn-sm btn-primary">
        <i class="ti ti-plus"></i> {{ __('Connect Account') }}
    </a>
@endsection

@section('content')
    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ti ti-check me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="ti ti-x me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="ti ti-alert-triangle me-1"></i> {{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('info'))
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="ti ti-info-circle me-1"></i> {{ session('info') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Platform Summary --}}
    @if($accounts->count() > 0)
        <div class="row mb-3">
            @php
                $platformCounts = $accounts->groupBy('platform')->map->count();
            @endphp
            @foreach($platformCounts as $platform => $count)
                <div class="col-auto">
                    <div class="badge bg-light text-dark px-3 py-2" style="font-size: 0.9rem;">
                        <i class="{{ $accounts->firstWhere('platform', $platform)->platform_icon }}" style="color: {{ $accounts->firstWhere('platform', $platform)->platform_color }};"></i>
                        {{ ucfirst($platform) }}: <strong>{{ $count }}</strong>
                    </div>
                </div>
            @endforeach
            <div class="col-auto">
                <div class="badge bg-primary px-3 py-2" style="font-size: 0.9rem;">
                    {{ __('Total') }}: <strong>{{ $accounts->count() }}</strong>
                </div>
            </div>
        </div>
    @endif

    <div class="row">
        @forelse($accounts as $account)
            <div class="col-xl-4 col-md-6">
                <div class="card" style="border-left: 4px solid {{ $account->platform_color }};">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="me-3">
                                <i class="{{ $account->platform_icon }}" style="font-size: 3rem; color: {{ $account->platform_color }};"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="mb-0">{{ $account->account_name }}</h5>
                                <small class="text-muted">{{ ucfirst($account->platform) }}</small>
                            </div>
                            <div>
                                <span class="badge bg-{{ $account->status == 'active' ? 'success' : ($account->status == 'expired' ? 'danger' : 'warning') }}">
                                    {{ ucfirst($account->status) }}
                                </span>
                            </div>
                        </div>

                        @if($account->page_name)
                            <div class="mb-2">
                                <small class="text-muted">{{ __('Page') }}:</small>
                                <p class="mb-0">{{ $account->page_name }}</p>
                            </div>
                        @endif

                        @if($account->page_id)
                            <div class="mb-2">
                                <small class="text-muted">{{ __('Page ID') }}:</small>
                                <p class="mb-0"><code>{{ $account->page_id }}</code></p>
                            </div>
                        @endif

                        <div class="mb-2">
                            <small class="text-muted">{{ __('Access Token') }}:</small>
                            <p class="mb-0">
                                @if($account->access_token)
                                    <span class="badge bg-success"><i class="ti ti-check"></i> {{ __('Connected') }}</span>
                                @else
                                    <span class="badge bg-warning"><i class="ti ti-alert-triangle"></i> {{ __('Not Set') }}</span>
                                @endif
                            </p>
                        </div>

                        <div class="mb-3">
                            <small class="text-muted">{{ __('Campaigns') }}:</small>
                            <strong>{{ $account->campaigns->count() }}</strong>
                            <span class="mx-2">|</span>
                            <small class="text-muted">{{ __('Connected') }}:</small>
                            <span>{{ $account->created_at->diffForHumans() }}</span>
                        </div>

                        <div class="d-flex gap-2 flex-wrap">
                            @if($account->access_token && in_array($account->platform, ['facebook', 'instagram']))
                                <form action="{{ route('marketing.accounts.verify', $account->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-success" title="{{ __('Verify Token') }}">
                                        <i class="ti ti-shield-check"></i>
                                    </button>
                                </form>
                            @endif
                            <a href="{{ route('marketing.accounts.edit', $account->id) }}" class="btn btn-sm btn-info flex-fill">
                                <i class="ti ti-edit"></i> {{ __('Edit') }}
                            </a>
                            <form action="{{ route('marketing.accounts.delete', $account->id) }}" method="POST" class="flex-fill" onsubmit="return confirm('{{ __('Are you sure?') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger w-100">
                                    <i class="ti ti-trash"></i> {{ __('Delete') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="ti ti-share text-muted" style="font-size: 5rem;"></i>
                        <h4 class="mt-3">{{ __('No Accounts Connected') }}</h4>
                        <p class="text-muted">{{ __('Connect your social media accounts to start managing campaigns') }}</p>
                        <a href="{{ route('marketing.accounts.create') }}" class="btn btn-primary mt-3">
                            <i class="ti ti-plus"></i> {{ __('Connect Account') }}
                        </a>
                    </div>
                </div>
            </div>
        @endforelse
    </div>
@endsection
