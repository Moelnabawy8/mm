@extends('layouts.admin')

@section('page-title')
    {{ __('Hiring - Jobs') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Websites') }}</li>
    <li class="breadcrumb-item">{{ __('Hiring Site') }}</li>
    <li class="breadcrumb-item">{{ __('Jobs') }}</li>
@endsection

@section('content')
    {{-- Navigation Tabs --}}
    <div class="row mb-3">
        <div class="col-12">
            <ul class="nav nav-pills">
                <li class="nav-item">
                    <a class="nav-link active" href="{{ route('websites.hiring.jobs') }}">
                        <i class="ti ti-briefcase me-1"></i>{{ __('Jobs') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('websites.hiring.posts') }}">
                        <i class="ti ti-article me-1"></i>{{ __('Blog Posts') }}
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row">
        @forelse ($jobs as $job)
            <div class="col-xl-4 col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start gap-3 mb-3">
                            @if(!empty($job['image']))
                                <img src="{{ $job['image'] }}" alt="" class="rounded" width="48" height="48" style="object-fit:cover;">
                            @else
                                <span class="avtar avtar-l bg-light-primary"><i class="ti ti-briefcase fs-3"></i></span>
                            @endif
                            <div>
                                <h6 class="mb-1">{{ $job['name'] ?? __('N/A') }}</h6>
                                @if(!empty($job['company']))
                                    <small class="text-muted">
                                        <i class="ti ti-building me-1"></i>{{ $job['company']['name'] ?? '' }}
                                    </small>
                                @endif
                            </div>
                        </div>

                        @if(!empty($job['description']))
                            <p class="text-muted small mb-2">{{ \Illuminate\Support\Str::limit(strip_tags($job['description']), 120) }}</p>
                        @endif

                        <div class="d-flex flex-wrap gap-2 mb-2">
                            @if(!empty($job['city']))
                                <span class="badge bg-light-secondary"><i class="ti ti-map-pin me-1"></i>{{ $job['city']['name'] ?? '' }}</span>
                            @endif
                            @if(!empty($job['state']))
                                <span class="badge bg-light-secondary">{{ $job['state']['name'] ?? '' }}</span>
                            @endif
                            @if(!empty($job['date']))
                                <span class="badge bg-light-primary"><i class="ti ti-calendar me-1"></i>{{ $job['date'] }}</span>
                            @endif
                        </div>

                        @if(!empty($job['company']['accounts']))
                            <small class="text-muted d-block mt-2">
                                <i class="ti ti-user me-1"></i>
                                {{ __('Contact') }}: {{ $job['company']['accounts'][0]['name'] ?? '' }}
                            </small>
                        @endif
                    </div>
                    @if(!empty($job['url']))
                        <div class="card-footer pt-0 border-0">
                            <a href="{{ $job['url'] }}" target="_blank" class="btn btn-sm btn-outline-primary w-100">
                                <i class="ti ti-external-link me-1"></i>{{ __('View Job') }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center text-muted py-5">
                        <i class="ti ti-briefcase-off fs-1 d-block mb-2"></i>
                        {{ __('No jobs found') }}
                    </div>
                </div>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if(is_array($jobs) && count($jobs) >= 12)
        <div class="row">
            <div class="col-12">
                <nav class="d-flex justify-content-center">
                    <ul class="pagination pagination-sm">
                        <li class="page-item {{ $currentPage <= 1 ? 'disabled' : '' }}">
                            <a class="page-link" href="{{ route('websites.hiring.jobs', ['page' => $currentPage - 1, 'company_id' => $companyId]) }}">&laquo;</a>
                        </li>
                        <li class="page-item active"><a class="page-link" href="#">{{ $currentPage }}</a></li>
                        <li class="page-item">
                            <a class="page-link" href="{{ route('websites.hiring.jobs', ['page' => $currentPage + 1, 'company_id' => $companyId]) }}">&raquo;</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    @endif
@endsection
