@extends('layouts.admin')

@section('page-title')
    {{ __('Hiring - Blog Posts') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Websites') }}</li>
    <li class="breadcrumb-item">{{ __('Hiring Site') }}</li>
    <li class="breadcrumb-item">{{ __('Blog Posts') }}</li>
@endsection

@section('content')
    {{-- Navigation Tabs --}}
    <div class="row mb-3">
        <div class="col-12">
            <ul class="nav nav-pills">
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('websites.hiring.jobs') }}">
                        <i class="ti ti-briefcase me-1"></i>{{ __('Jobs') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="{{ route('websites.hiring.posts') }}">
                        <i class="ti ti-article me-1"></i>{{ __('Blog Posts') }}
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row">
        @forelse ($posts as $post)
            <div class="col-xl-4 col-md-6 mb-3">
                <div class="card h-100">
                    @if(!empty($post['image']))
                        <img src="{{ $post['image'] }}" class="card-img-top" alt="{{ $post['name'] ?? '' }}" style="height:180px; object-fit:cover;">
                    @endif
                    <div class="card-body">
                        <h6 class="card-title mb-2">{{ $post['name'] ?? __('N/A') }}</h6>

                        @if(!empty($post['description']))
                            <p class="text-muted small mb-2">{{ \Illuminate\Support\Str::limit(strip_tags($post['description']), 100) }}</p>
                        @endif

                        @if(!empty($post['categories']))
                            <div class="mb-2">
                                @foreach ($post['categories'] as $cat)
                                    <span class="badge bg-light-primary">{{ $cat['name'] ?? '' }}</span>
                                @endforeach
                            </div>
                        @endif

                        @if(!empty($post['tags']))
                            <div class="mb-2">
                                @foreach (array_slice($post['tags'], 0, 3) as $tag)
                                    <span class="badge bg-light-secondary">{{ $tag['name'] ?? '' }}</span>
                                @endforeach
                            </div>
                        @endif

                        <small class="text-muted">
                            @if(!empty($post['created_at']))
                                <i class="ti ti-calendar me-1"></i>{{ \Carbon\Carbon::parse($post['created_at'])->format('M d, Y') }}
                            @endif
                        </small>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center text-muted py-5">
                        <i class="ti ti-article-off fs-1 d-block mb-2"></i>
                        {{ __('No posts found') }}
                    </div>
                </div>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if(is_array($posts) && count($posts) >= 10)
        <div class="row">
            <div class="col-12">
                <nav class="d-flex justify-content-center">
                    <ul class="pagination pagination-sm">
                        <li class="page-item {{ $currentPage <= 1 ? 'disabled' : '' }}">
                            <a class="page-link" href="{{ route('websites.hiring.posts', ['page' => $currentPage - 1]) }}">&laquo;</a>
                        </li>
                        <li class="page-item active"><a class="page-link" href="#">{{ $currentPage }}</a></li>
                        <li class="page-item">
                            <a class="page-link" href="{{ route('websites.hiring.posts', ['page' => $currentPage + 1]) }}">&raquo;</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    @endif
@endsection
